<?php

namespace Netlogix\Nxvarnish\Middleware;

use Netlogix\Nxvarnish\Cache\Backend\RetrievableTagsBackendInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class CacheTagMiddleware implements MiddlewareInterface
{

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $controller = $this->getTypoScriptFrontendController();
        if (
            !($response instanceof NullResponse)
            && $controller instanceof TypoScriptFrontendController
            && $controller->isOutputting()) {
            if (
                $request->getAttribute('normalizedParams')->isBehindReverseProxy()
                || $request->hasHeader('X-Esi')
            ) {
                $response = $response->withHeader('X-Cache-Tags', ';' . implode(';', $this->getPageCacheTags()) . ';');
            }
        }
        return $response;
    }

    protected function getTypoScriptFrontendController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    protected function getPageCacheTags()
    {
        $controller = $this->getTypoScriptFrontendController();
        if ($controller->isGeneratePage()) {
            $pageCacheTags = $this->getTypoScriptFrontendController()->getPageCacheTags();
        } else {
            $pageCacheBackend = GeneralUtility::makeInstance(ObjectManager::class)
                ->get(CacheManager::class)
                ->getCache('cache_pages')
                ->getBackend();

            assert($pageCacheBackend instanceof RetrievableTagsBackendInterface);
            $pageCacheTags = $pageCacheBackend->getTags($controller->newHash);
        }

        $pageCacheTags = array_unique($pageCacheTags);
        $pageCacheTags = $this->simplifyCacheTags($pageCacheTags);
        $pageCacheTags = $this->compressCacheTags($pageCacheTags);

        return $pageCacheTags;
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