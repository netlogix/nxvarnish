<?php

namespace Netlogix\Nxvarnish\Cache\Backend;

use TYPO3\CMS\Core\Cache\Backend\AbstractBackend;
use TYPO3\CMS\Core\Cache\Backend\TaggableBackendInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception;
use TYPO3\CMS\Core\Cache\Exception\InvalidDataException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RetrievableTagsProxyBackend extends AbstractBackend implements RetrievableTagsBackendInterface
{

    /**
     * @var TaggableBackendInterface
     */
    protected $proxyBackend;

    /**
     * @var boolean Indicates wether data is compressed or not (requires php zlib)
     */
    protected $compression = false;

    /**
     * @var integer -1 to 9, indicates zlib compression level: -1 = default level 6, 0 = no compression, 9 maximum compression
     */
    protected $compressionLevel = -1;

    /**
     * @var string
     */
    protected $proxyCacheIdentifier;

    /**
     * Set cache frontend instance and calculate data and tags table name
     */
    public function setCache(FrontendInterface $cache)
    {
        parent::setCache($cache);
        $this->proxyCacheIdentifier = $this->cacheIdentifier . '_nxcachetags_proxy';
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        if (!$cacheManager->hasCache($this->proxyCacheIdentifier)) {
            $cacheManager->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);
        }
        $proxyCache = $cacheManager->getCache($this->proxyCacheIdentifier);
        $this->proxyBackend = $proxyCache->getBackend();
    }

    /**
     * Enable data compression
     */
    public function setCompression(bool $compression)
    {
        $this->compression = $compression;
    }

    /**
     * Set data compression level.
     * If compression is enabled and this is not set,
     * gzcompress default level will be used
     *
     * @param integer -1 to 9: Compression level
     */
    public function setCompressionLevel(int $compressionLevel)
    {
        if ($compressionLevel >= -1 && $compressionLevel <= 9) {
            $this->compressionLevel = $compressionLevel;
        }
    }

    /**
     * Saves data in the cache.
     *
     * @param string $entryIdentifier An identifier for this specific cache entry
     * @param string $data The data to be stored
     * @param array $tags Tags to associate with this cache entry. If the backend does not support tags, this option can be ignored.
     * @param int $lifetime Lifetime of this cache entry in seconds. If NULL is specified, the default lifetime is used. "0" means unlimited lifetime.
     * @throws Exception if no cache frontend has been set.
     * @throws InvalidDataException if the data is not a string
     */
    public function set($entryIdentifier, $data, array $tags = [], $lifetime = null)
    {
        $cachedHeaders = [];
        foreach (headers_list() as $header) {
            if (in_array(strtolower(substr($header, 0, 4)), ['x-nx', 'x-cr'])) {
                $cachedHeaders[] = $header;
            }
        }
        $this->proxyBackend->set($entryIdentifier, serialize([$data, $tags, $cachedHeaders]), $tags, $lifetime);
    }

    /**
     * Returns all cache tags being stored for this particular identifier.
     *
     * @param string $entryIdentifier
     * @return string[]
     */
    public function getTags(string $entryIdentifier): array
    {
        $result = unserialize($this->proxyBackend->get($entryIdentifier));
        if (!$result) {
            return [];
        } else {
            return $result[1];
        }
    }

    /**
     * Loads data from the cache.
     *
     * @param string $entryIdentifier An identifier which describes the cache entry to load
     * @return mixed The cache entry's content as a string or FALSE if the cache entry could not be loaded
     */
    public function get($entryIdentifier)
    {
        $result = unserialize($this->proxyBackend->get($entryIdentifier));
        if (!$result) {
            return false;
        } else {
            return $result[0];
        }
    }

    /**
     * Checks if a cache entry with the specified identifier exists.
     *
     * @param string $entryIdentifier An identifier specifying the cache entry
     * @return bool TRUE if such an entry exists, FALSE if not
     */
    public function has($entryIdentifier)
    {
        return $this->proxyBackend->has($entryIdentifier);
    }

    /**
     * Removes all cache entries matching the specified identifier.
     * Usually this only affects one entry but if - for what reason ever -
     * old entries for the identifier still exist, they are removed as well.
     *
     * @param string $entryIdentifier Specifies the cache entry to remove
     * @return bool TRUE if (at least) an entry could be removed or FALSE if no entry was found
     */
    public function remove($entryIdentifier)
    {
        return $this->proxyBackend->remove($entryIdentifier);
    }

    /**
     * Removes all cache entries of this cache.
     */
    public function flush()
    {
        $this->proxyBackend->flush();
    }

    /**
     * Does garbage collection
     */
    public function collectGarbage()
    {
        $this->proxyBackend->collectGarbage();
    }

    /**
     * Removes all cache entries of this cache which are tagged by the specified tag.
     *
     * @param string $tag The tag the entries must have
     */
    public function flushByTag($tag)
    {
        $this->proxyBackend->flushByTag($tag);
    }

    /**
     * Finds and returns all cache entry identifiers which are tagged by the
     * specified tag
     *
     * @param string $tag The tag to search for
     * @return array An array with identifiers of all matching entries. An empty array if no entries matched
     */
    public function findIdentifiersByTag($tag)
    {
        return $this->proxyBackend->findIdentifiersByTag($tag);
    }
}
