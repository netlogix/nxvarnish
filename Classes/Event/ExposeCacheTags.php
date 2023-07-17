<?php

namespace Netlogix\Nxvarnish\Event;

use Netlogix\Nxvarnish\Service\ExposeService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

final class ExposeCacheTags
{
    public function __invoke(AfterCacheableContentIsGeneratedEvent $event): void
    {
        if (
            $event->getRequest()->getAttribute('normalizedParams')->isBehindReverseProxy()
            || $event->getRequest()->hasHeader('X-Esi')
        ) {
            // add headers to typoScript config. this means that headers will be cached together with page content.
            // if the page is fetched from cache then the headers will be fetched as well and sent again.
            $event->getController()->config['config']['additionalHeaders.'][] = [
                'header' => 'X-Cache-Tags: ' . ';' . implode(
                        ';',
                        GeneralUtility::makeInstance(ExposeService::class)->getPageCacheTags($event->getController())
                    ) . ';'
            ];
        }
    }
}
