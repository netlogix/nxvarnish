<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Hooks;

use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class ContentPostProcHook {

    public function cached(array $params, TypoScriptFrontendController &$tsfe) {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();

        if (
            $request->getAttribute('normalizedParams')->isBehindReverseProxy()
            || $request->hasHeader('X-Esi')
        ) {
            // add headers to typoScript config. this means that headers will be cached together with page content.
            // if the page is fetched from cache then the headers will be fetched as well and sent again.
            $tsfe->config['config']['additionalHeaders.'][] = [ 'header' => 'X-Cache-Tags: ' . ';' . implode(';', $this->getPageCacheTags($tsfe)) . ';'];
        }
    }

    protected function getPageCacheTags(TypoScriptFrontendController $tsfe)
    {
        $pageCacheTags = $tsfe->getPageCacheTags();

        $pageCacheTags = array_unique($pageCacheTags);
        $pageCacheTags = $this->simplifyCacheTags($pageCacheTags);
        return $this->compressCacheTags($pageCacheTags);
    }

    /**
     * Simplify cache tags and remove all record specific cache tags for which also the table is tagged
     */
    protected function simplifyCacheTags(array $cacheTags): array
    {
        $tableCacheTags = [];

        foreach ($cacheTags as $cacheTag) {
            if (isset($GLOBALS['TCA'][$cacheTag])) {
                $tableCacheTags[] = $cacheTag;
            }
        }

        $recordCacheTagPattern = '/^(?:' . implode('|', array_map('preg_quote', $tableCacheTags, ['/'])) . ')_\d+$/';

        foreach ($cacheTags as $key => $cacheTag) {
            if (preg_match($recordCacheTagPattern, $cacheTag) === 1) {
                unset($cacheTags[$key]);
            }
        }

        return $cacheTags;
    }

    /**
     * Compress cache tags to avoid too big headers. Multiple cache tags for different records of the same table are
     * combined to a single tag containing the table and the list of uids in the form
     *
     * table{,uid1,uid2,}
     */
    protected function compressCacheTags(array $cacheTags)
    {
        $tagsToCompress = [];

        foreach ($cacheTags as $key => $cacheTag) {
            if (preg_match('/^([a-z0-9_]+)_(\d+)$/i', $cacheTag, $matches) === 1) {
                unset($cacheTags[$key]);
                $table = $matches[1];
                $uid = $matches[2];
                if (!isset($tagsToCompress[$table])) {
                    $tagsToCompress[$table] = [];
                }
                $tagsToCompress[$table][] = $uid;
            }
        }

        foreach ($tagsToCompress as $table => $uids) {
            $cacheTags[] = $table . '{,' . implode(',', $uids) . ',}';
        }

        return $cacheTags;
    }
}
