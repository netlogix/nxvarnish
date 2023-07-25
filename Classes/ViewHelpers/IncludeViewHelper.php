<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\ViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\RequestInterface as ExtbaseRequestInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder as ExtbaseUriBuilder;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Typolink\LinkFactory;
use TYPO3\CMS\Frontend\Typolink\UnableToLinkException;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * Wrap esi:include and provide some debug information
 */
class IncludeViewHelper extends AbstractTagBasedViewHelper implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected $tagName = 'esi:include';

    protected $esiDebugCommentTemplate = '%1$s';

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerUniversalTagAttributes();
        $this->registerTagAttribute('target', 'string', 'Target of link', false);
        $this->registerTagAttribute('rel', 'string', 'Specifies the relationship between the current document and the linked document', false);
        $this->registerArgument('pageUid', 'int', 'Target page. See TypoLink destination');
        $this->registerArgument('pageType', 'int', 'Type of the target page. See typolink.parameter');
        $this->registerArgument('noCache', 'bool', 'Set this to disable caching for the target page. You should not need this.');
        $this->registerArgument('language', 'string', 'link to a specific language - defaults to the current language, use a language ID or "current" to enforce a specific language', false);
        $this->registerArgument('section', 'string', 'The anchor to be added to the URI');
        $this->registerArgument('linkAccessRestrictedPages', 'bool', 'If set, links pointing to access restricted pages will still link to the page even though the page cannot be accessed.');
        $this->registerArgument('additionalParams', 'array', 'Additional query parameters that won\'t be prefixed like $arguments (overrule $arguments)');
        $this->registerArgument('absolute', 'bool', 'If set, the URI of the rendered link is absolute');
        $this->registerArgument('addQueryString', 'string', 'If set, the current query parameters will be kept in the URL. If set to "untrusted", then ALL query parameters will be added. Be aware, that this might lead to problems when the generated link is cached.', false, false);
        $this->registerArgument('argumentsToBeExcludedFromQueryString', 'array', 'Arguments to be removed from the URI. Only active if $addQueryString = TRUE');

        $this->registerArgument('src', 'string', '', false, null);
    }

    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager)
    {
        $settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );

        if (array_key_exists('tx_nxvarnish.', (array)$settings['config.']) && array_key_exists('settings.', (array)$settings['config.']['tx_nxvarnish.'])) {
            $extensionConfiguration = (array)$settings['config.']['tx_nxvarnish.']['settings.'];
            $this->esiDebugCommentTemplate = $extensionConfiguration['esiDebugComment'];
        }
    }

    public function render(): string
    {
        /** @var RenderingContext $renderingContext */
        $renderingContext = $this->renderingContext;
        $request = $renderingContext->getRequest();

        if ($this->arguments['src'] !== null && $this->arguments['src'] !== '') {
            $src = $this->arguments['src'];
        } else if ($request instanceof ExtbaseRequestInterface) {
            $src = $this->renderWithExtbaseContext($request);
        } else if ($request instanceof ServerRequestInterface && ApplicationType::fromRequest($request)->isFrontend()) {
            // Use the regular typolink functionality.
            $src = $this->renderFrontendLinkWithCoreContext($request);
        } else {
            throw new \RuntimeException(
                'The rendering context of ViewHelper esi:include is missing a valid request object.',
                1639819269
            );
        }

        if ($src === '') {
            return '';
        }

        // Use closing tag for javascript fallback
        if (!$request->getAttribute('normalizedParams')->isBehindReverseProxy()) {
            $this->tag->forceClosingTag(true);
        }
        $this->tag->addAttribute('src', $src);
        $result = $this->tag->render();

        if ($this->esiDebugCommentTemplate && $this->esiDebugCommentTemplate !== '%s' && $this->esiDebugCommentTemplate !== '%1$s') {
            return $this->wrapTagContentInDebugInformation($result, $src);
        } else {
            return $result;
        }
    }

    protected function renderWithExtbaseContext(ExtbaseRequestInterface $request): string
    {
        $pageUid = isset($this->arguments['pageUid']) ? (int)$this->arguments['pageUid'] : null;
        $pageType = isset($this->arguments['pageType']) ? (int)$this->arguments['pageType'] : 0;
        $noCache = isset($this->arguments['noCache']) && (bool)$this->arguments['noCache'];
        $section = isset($this->arguments['section']) ? (string)$this->arguments['section'] : '';
        $language = $this->arguments['language'] ?? null;
        $linkAccessRestrictedPages = isset($this->arguments['linkAccessRestrictedPages']) && (bool)$this->arguments['linkAccessRestrictedPages'];
        $additionalParams = isset($this->arguments['additionalParams']) ? (array)$this->arguments['additionalParams'] : [];
        $absolute = isset($this->arguments['absolute']) && (bool)$this->arguments['absolute'];
        $addQueryString = $this->arguments['addQueryString'] ?? false;
        $argumentsToBeExcludedFromQueryString = isset($this->arguments['argumentsToBeExcludedFromQueryString']) ? (array)$this->arguments['argumentsToBeExcludedFromQueryString'] : [];

        $uriBuilder = GeneralUtility::makeInstance(ExtbaseUriBuilder::class);
        $uriBuilder->reset()
            ->setRequest($request)
            ->setTargetPageType($pageType)
            ->setNoCache($noCache)
            ->setSection($section)
            ->setLanguage($language)
            ->setLinkAccessRestrictedPages($linkAccessRestrictedPages)
            ->setArguments($additionalParams)
            ->setCreateAbsoluteUri($absolute)
            ->setAddQueryString($addQueryString)
            ->setArgumentsToBeExcludedFromQueryString($argumentsToBeExcludedFromQueryString);

        if (MathUtility::canBeInterpretedAsInteger($pageUid)) {
            $uriBuilder->setTargetPageUid((int)$pageUid);
        }

        return $uriBuilder->build();
    }

    protected function renderFrontendLinkWithCoreContext(ServerRequestInterface $request): string
    {
        $pageUid = isset($this->arguments['pageUid']) ? (int)$this->arguments['pageUid'] : 'current';
        $pageType = isset($this->arguments['pageType']) ? (int)$this->arguments['pageType'] : 0;
        $noCache = isset($this->arguments['noCache']) && (bool)$this->arguments['noCache'];
        $section = isset($this->arguments['section']) ? (string)$this->arguments['section'] : '';
        $language = $this->arguments['language'] ?? null;
        $linkAccessRestrictedPages = isset($this->arguments['linkAccessRestrictedPages']) && (bool)$this->arguments['linkAccessRestrictedPages'];
        $additionalParams = isset($this->arguments['additionalParams']) ? (array)$this->arguments['additionalParams'] : [];
        $absolute = isset($this->arguments['absolute']) && (bool)$this->arguments['absolute'];
        $addQueryString = $this->arguments['addQueryString'] ?? false;
        $argumentsToBeExcludedFromQueryString = isset($this->arguments['argumentsToBeExcludedFromQueryString']) ? (array)$this->arguments['argumentsToBeExcludedFromQueryString'] : [];

        $typolinkConfiguration = [
            'parameter' => $pageUid,
        ];
        if ($pageType) {
            $typolinkConfiguration['parameter'] .= ',' . $pageType;
        }
        if ($noCache) {
            $typolinkConfiguration['no_cache'] = 1;
        }
        if ($language !== null) {
            $typolinkConfiguration['language'] = $language;
        }
        if ($section) {
            $typolinkConfiguration['section'] = $section;
        }
        if ($linkAccessRestrictedPages) {
            $typolinkConfiguration['linkAccessRestrictedPages'] = 1;
        }
        if ($additionalParams) {
            $typolinkConfiguration['additionalParams'] = HttpUtility::buildQueryString($additionalParams, '&');
        }
        if ($absolute) {
            $typolinkConfiguration['forceAbsoluteUrl'] = true;
        }
        if ($addQueryString && $addQueryString !== 'false') {
            $typolinkConfiguration['addQueryString'] = $addQueryString;
            if ($argumentsToBeExcludedFromQueryString !== []) {
                $typolinkConfiguration['addQueryString.']['exclude'] = implode(',', $argumentsToBeExcludedFromQueryString);
            }
        }

        try {
            $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            $cObj->setRequest($request);
            $linkFactory = GeneralUtility::makeInstance(LinkFactory::class);
            $linkResult = $linkFactory->create((string)$this->renderChildren(), $typolinkConfiguration, $cObj);
            return $linkResult->getUrl();
        } catch (UnableToLinkException $e) {
            $result = '';
        }
        return $result;
    }

    protected function wrapTagContentInDebugInformation(string $content, string $src): string
    {
        $debugText = $this->renderChildren();
        if ($debugText) {
            $debugText = trim($debugText);
            $debugText = str_replace(['<!--', '-->'], '', $debugText);
        } else {
            $debugText = 'None given';
        }

        return sprintf($this->esiDebugCommentTemplate, $content, $src, $debugText);
    }

}
