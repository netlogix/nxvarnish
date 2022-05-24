<?php
declare(strict_types=1);

namespace Netlogix\Nxvarnish\ViewHelpers;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Fluid\ViewHelpers\Link\PageViewHelper;

/**
 * Wrap esi:include and provide some debug information
 */
class IncludeViewHelper extends PageViewHelper implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected $tagName = 'esi:include';

    protected $esiDebugCommentTemplate = '%1$s';

    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('src', 'string', '', false, null);
    }

    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager)
    {
        $settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );
        $extensionConfiguration = (array)$settings['config.']['tx_nxvarnish.']['settings.'];
        $this->esiDebugCommentTemplate = $extensionConfiguration['esiDebugComment'];
    }

    public function render(): string
    {
        $pageUid = isset($this->arguments['pageUid']) ? (int)$this->arguments['pageUid'] : null;
        $pageType = isset($this->arguments['pageType']) ? (int)$this->arguments['pageType'] : 0;
        $noCache = isset($this->arguments['noCache']) ? (bool)$this->arguments['noCache'] : false;
        $noCacheHash = isset($this->arguments['noCacheHash']) ? (bool)$this->arguments['noCacheHash'] : false;
        $section = isset($this->arguments['section']) ? (string)$this->arguments['section'] : '';
        $linkAccessRestrictedPages = isset($this->arguments['linkAccessRestrictedPages']) ? (bool)$this->arguments['linkAccessRestrictedPages'] : false;
        $additionalParams = isset($this->arguments['additionalParams']) ? (array)$this->arguments['additionalParams'] : [];
        $absolute = isset($this->arguments['absolute']) ? (bool)$this->arguments['absolute'] : false;
        $addQueryString = isset($this->arguments['addQueryString']) ? (bool)$this->arguments['addQueryString'] : false;
        $argumentsToBeExcludedFromQueryString = isset($this->arguments['argumentsToBeExcludedFromQueryString']) ? (array)$this->arguments['argumentsToBeExcludedFromQueryString'] : [];
        $addQueryStringMethod = $this->arguments['addQueryStringMethod'] ?? '';
        $src = $this->arguments['src'] ?? null;

        if ($src === null) {
            $uriBuilder = $this->renderingContext->getControllerContext()->getUriBuilder();
            assert($uriBuilder instanceof UriBuilder);
            $src = $uriBuilder->reset()
                ->setTargetPageUid($pageUid)
                ->setTargetPageType($pageType)
                ->setNoCache($noCache)
                ->setSection($section)
                ->setLinkAccessRestrictedPages($linkAccessRestrictedPages)
                ->setArguments($additionalParams)
                ->setCreateAbsoluteUri($absolute)
                ->setAddQueryString($addQueryString)
                ->setArgumentsToBeExcludedFromQueryString($argumentsToBeExcludedFromQueryString)
                ->setAddQueryStringMethod($addQueryStringMethod)
                ->build();
        }
        $src = str_replace('https://', 'http://', $src);

        if ((string)$src === '') {
            $this->logger->error('Trying to esi:include with no valid URI!', [
                'pageUid' => $pageUid,
                'additionalParams' => $additionalParams,
                'pageType' => $pageType,
                'linkAccessRestrictedPages' => $linkAccessRestrictedPages,
                'absolute' => $absolute,
                'argumentsToBeExcludedFromQueryString' => $argumentsToBeExcludedFromQueryString,
            ]);
            return '';
        }

        // Use closing tag for javascript fallback
        if (!GeneralUtility::getIndpEnv('TYPO3_REV_PROXY')) {
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
