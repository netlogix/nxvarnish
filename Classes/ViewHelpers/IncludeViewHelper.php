<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\ViewHelpers;

use Override;
use Closure;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface as ExtbaseRequestInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder as ExtbaseUriBuilder;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Typolink\LinkFactory;
use TYPO3\CMS\Frontend\Typolink\UnableToLinkException;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * Wrap esi:include and provide some debug information
 */
class IncludeViewHelper extends AbstractTagBasedViewHelper
{
    protected $tagName = 'esi:include';

    #[Override]
    public function initializeArguments(): void
    {
        $this->registerArgument('pageUid', 'int', 'target PID');
        $this->registerArgument(
            'additionalParams',
            'array',
            'query parameters to be attached to the resulting URI',
            false,
            [],
        );
        $this->registerArgument('pageType', 'int', 'type of the target page. See typolink.parameter', false, 0);
        $this->registerArgument(
            'noCache',
            'bool',
            'set this to disable caching for the target page. You should not need this.',
            false,
            false,
        );
        $this->registerArgument(
            'language',
            'string',
            'link to a specific language - defaults to the current language, use a language ID or "current" to enforce a specific language',
        );
        $this->registerArgument('section', 'string', 'the anchor to be added to the URI', false, '');
        $this->registerArgument(
            'linkAccessRestrictedPages',
            'bool',
            'If set, links pointing to access restricted pages will still link to the page even though the page cannot be accessed.',
            false,
            false,
        );
        $this->registerArgument(
            'absolute',
            'bool',
            'If set, the URI of the rendered link is absolute',
            false,
            false,
        );
        $this->registerArgument(
            'addQueryString',
            'string',
            'If set, the current query parameters will be kept in the URL. If set to "untrusted", then ALL query parameters will be added. Be aware, that this might lead to problems when the generated link is cached.',
            false,
            false,
        );
        $this->registerArgument(
            'argumentsToBeExcludedFromQueryString',
            'array',
            'arguments to be removed from the URI. Only active if $addQueryString = TRUE',
            false,
            [],
        );

        $this->registerArgument('src', 'string', '', false, null);
    }

    #[Override]
    public function render(): string
    {
        $request = null;
        if ($this->renderingContext->hasAttribute(ServerRequestInterface::class)) {
            $request = $this->renderingContext->getAttribute(ServerRequestInterface::class);
        }

        if ($this->arguments['src'] !== null && $this->arguments['src'] !== '') {
            $src = $this->arguments['src'];
        } elseif ($request instanceof ExtbaseRequestInterface) {
            $src = self::renderWithExtbaseContext($request, $this->arguments);
        } elseif ($request instanceof ServerRequestInterface) {
            if (ApplicationType::fromRequest($request)->isBackend()) {
                throw new RuntimeException(
                    'ViewHelper esi:include is not supported in backend context.',
                    1639819268,
                );
            }

            // Use the regular typolink functionality.
            $src = self::renderFrontendLinkWithCoreContext($request, $this->arguments, $this->renderChildren(...));
        } else {
            throw new RuntimeException(
                'The rendering context of ViewHelper esi:include is missing a valid request object.',
                1639819269,
            );
        }

        if ($src === '') {
            return '';
        }

        $this->tag->addAttribute('src', $src);
        return $this->tag->render();
    }

    protected static function renderFrontendLinkWithCoreContext(
        ServerRequestInterface $request,
        array $arguments,
        Closure $renderChildrenClosure,
    ): string {
        $pageUid = isset($arguments['pageUid']) ? (int) $arguments['pageUid'] : 'current';
        $pageType = isset($arguments['pageType']) ? (int) $arguments['pageType'] : 0;
        $noCache = isset($arguments['noCache']) && (bool) $arguments['noCache'];
        $section = isset($arguments['section']) ? (string) $arguments['section'] : '';
        $language = isset($arguments['language']) ? (string) $arguments['language'] : null;
        $linkAccessRestrictedPages =
            isset($arguments['linkAccessRestrictedPages']) && (bool) $arguments['linkAccessRestrictedPages'];
        $additionalParams = isset($arguments['additionalParams']) ? (array) $arguments['additionalParams'] : [];
        $absolute = isset($arguments['absolute']) && (bool) $arguments['absolute'];
        $addQueryString = $arguments['addQueryString'] ?? false;
        $argumentsToBeExcludedFromQueryString = isset($arguments['argumentsToBeExcludedFromQueryString'])
            ? (array) $arguments['argumentsToBeExcludedFromQueryString']
            : [];

        $typolinkConfiguration = [
            'parameter' => $pageUid,
        ];
        if ($pageType !== 0) {
            $typolinkConfiguration['parameter'] .= ',' . $pageType;
        }

        if ($noCache) {
            $typolinkConfiguration['no_cache'] = 1;
        }

        if ($language !== null) {
            $typolinkConfiguration['language'] = $language;
        }

        if ($section !== '' && $section !== '0') {
            $typolinkConfiguration['section'] = $section;
        }

        if ($linkAccessRestrictedPages) {
            $typolinkConfiguration['linkAccessRestrictedPages'] = 1;
        }

        if ($additionalParams !== []) {
            $typolinkConfiguration['additionalParams'] = HttpUtility::buildQueryString($additionalParams, '&');
        }

        if ($absolute) {
            $typolinkConfiguration['forceAbsoluteUrl'] = true;
        }

        if ($addQueryString && $addQueryString !== 'false') {
            $typolinkConfiguration['addQueryString'] = $addQueryString;
            if ($argumentsToBeExcludedFromQueryString !== []) {
                $typolinkConfiguration['addQueryString.']['exclude'] = implode(
                    ',',
                    $argumentsToBeExcludedFromQueryString,
                );
            }
        }

        try {
            $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            $cObj->setRequest($request);
            $linkFactory = GeneralUtility::makeInstance(LinkFactory::class);
            $linkResult = $linkFactory->create((string) $renderChildrenClosure(), $typolinkConfiguration, $cObj);
            return $linkResult->getUrl();
        } catch (UnableToLinkException) {
            return (string) $renderChildrenClosure();
        }
    }

    protected static function renderWithExtbaseContext(ExtbaseRequestInterface $request, array $arguments): string
    {
        $pageUid = $arguments['pageUid'];
        $additionalParams = $arguments['additionalParams'];
        $pageType = (int) ($arguments['pageType'] ?? 0);
        $noCache = $arguments['noCache'];
        $section = $arguments['section'];
        $language = isset($arguments['language']) ? (string) $arguments['language'] : null;
        $linkAccessRestrictedPages = $arguments['linkAccessRestrictedPages'];
        $absolute = $arguments['absolute'];
        $addQueryString = $arguments['addQueryString'] ?? false;
        $argumentsToBeExcludedFromQueryString = $arguments['argumentsToBeExcludedFromQueryString'];

        $uriBuilder = GeneralUtility::makeInstance(ExtbaseUriBuilder::class);
        $uri = $uriBuilder
            ->reset()
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
            $uriBuilder->setTargetPageUid((int) $pageUid);
        }

        return $uri->build();
    }
}
