<?php

namespace Netlogix\Nxvarnish\Service;

use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

final class ExposeService
{

    public function getPageCacheTags(TypoScriptFrontendController $typoScriptFrontendController)
    {

        $pageCacheTags = $typoScriptFrontendController->getPageCacheTags();
        $pageCacheTags = array_unique($pageCacheTags);
        $pageCacheTags = $this->simplifyCacheTags($pageCacheTags);
        $pageCacheTags[] = 'pageId_' . $typoScriptFrontendController->id;
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
        if(empty($tableCacheTags)) {
            return $cacheTags;
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
