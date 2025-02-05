<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Cache\CacheTag;

final readonly class ExposeCacheTags implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (
            $request->getAttribute('normalizedParams')->isBehindReverseProxy() === false &&
            !$request->hasHeader('x-varnish')
        ) {
            return $response;
        }

        $cacheDataCollector = $request->getAttribute('frontend.cache.collector');
        $cacheTags = array_map(
            fn(CacheTag $cacheTag): string => $cacheTag->name,
            $cacheDataCollector->getCacheTags(),
        );
        $cacheTags = $this->simplifyCacheTags($cacheTags);
        $cacheTags = $this->compressCacheTags($cacheTags);
        return $response->withHeader('X-Cache-Tags', implode(';', $cacheTags) . ';');
    }

    /**
     * Simplify cache tags and remove all record specific cache tags for which also the table is tagged
     */
    protected function simplifyCacheTags(array $cacheTags): array
    {
        $tableCacheTags = [];
        foreach ($cacheTags as $cacheTag) {
            if (array_key_exists($cacheTag, $GLOBALS['TCA'] ?? [])) {
                $tableCacheTags[] = $cacheTag;
            }
        }

        if ($tableCacheTags === []) {
            return $cacheTags;
        }

        $recordCacheTagPattern =
            '/^(?:' . implode('|', array_map('preg_quote', $tableCacheTags, ['/'])) . ')_\d+$/';

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
    protected function compressCacheTags(array $cacheTags): array
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

        sort($cacheTags);
        return $cacheTags;
    }
}
