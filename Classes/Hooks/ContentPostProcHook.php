<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Hooks;

use Netlogix\Nxvarnish\Service\ExposeService;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class ContentPostProcHook
{

    public function cached(array $params, TypoScriptFrontendController &$tsfe): void
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();
        if (
            $request->getAttribute('normalizedParams')->isBehindReverseProxy()
            || $request->hasHeader('X-Esi')
        ) {
            // add headers to typoScript config. this means that headers will be cached together with page content.
            // if the page is fetched from cache then the headers will be fetched as well and sent again.
            $tsfe->config['config']['additionalHeaders.'][] = [
                'header' => 'X-Cache-Tags: ' . ';' . implode(
                        ';',
                        GeneralUtility::makeInstance(ExposeService::class)->getPageCacheTags($tsfe)
                    ) . ';'
            ];
        }
    }

}
