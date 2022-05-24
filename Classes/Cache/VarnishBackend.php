<?php
declare(strict_types=1);

namespace Netlogix\Nxvarnish\Cache;

use Netlogix\Nxvarnish\Service\CurlVarnishService;
use TYPO3\CMS\Core\Cache\Backend\TaggableBackendInterface;
use TYPO3\CMS\Core\Cache\Exception;
use TYPO3\CMS\Core\Cache\Exception\InvalidDataException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class VarnishBackend implements TaggableBackendInterface
{

    protected $extensionConfiguration = [];

    /**
     * Create an instance of the CURLHTTP cachemanager. IT takes one parameter, the HTTP address
     * (including http://) that the Varnish server is running on. If this parameter is specified
     * This one is used, otherwise, the host of the URL that needs to cleared is used.
     */
    public function __construct()
    {
        $this->extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('nxvarnish');
    }

    /**
     * According to interface, should load data from the cache. Does nothing.
     *
     * @param string $entryIdentifier An identifier which describes the cache entry to load
     * @return mixed The cache entry's content as a string or FALSE if the cache entry could not be loaded
     */
    public function get($entryIdentifier)
    {
        return null;
    }

    /**
     * According to interface, should sets a reference to the cache frontend which uses this backend. Does nothing.
     *
     * @param FrontendInterface $cache The frontend for this backend
     */
    public function setCache(FrontendInterface $cache): void
    {
    }

    /**
     * According to interface, should save data in the cache. Does nothing.
     *
     * @param string $entryIdentifier An identifier for this specific cache entry
     * @param string $data The data to be stored
     * @param array $tags Tags to associate with this cache entry. If the backend does not support tags, this option can be ignored.
     * @param int $lifetime Lifetime of this cache entry in seconds. If NULL is specified, the default lifetime is used. "0" means unlimited lifetime.
     * @throws Exception if no cache frontend has been set.
     * @throws InvalidDataException if the data is not a string
     */
    public function set($entryIdentifier, $data, array $tags = [], $lifetime = null): void
    {
    }

    /**
     * According to interface, should check if a cache entry with the specified identifier exists. Does nothing.
     *
     * @param string $entryIdentifier An identifier specifying the cache entry
     * @return bool TRUE if such an entry exists, FALSE if not
     */
    public function has($entryIdentifier): bool
    {
        return false;
    }

    /**
     * According to interface, should remove all cache entries matching the specified identifier. Does nothing.
     * Usually this only affects one entry but if - for what reason ever -
     * old entries for the identifier still exist, they are removed as well.
     *
     * @param string $entryIdentifier Specifies the cache entry to remove
     * @return bool TRUE if (at least) an entry could be removed or FALSE if no entry was found
     */
    public function remove($entryIdentifier): bool
    {
        return false;
    }

    /**
     * Removes all cache entries of this cache.
     */
    public function flush(): void
    {
        $this->flushVarnishByTag('.*');
    }

    protected function flushVarnishByTag(string $tag): void
    {
        $tag = $this->modifyTag($tag);

        if (!empty($this->extensionConfiguration['varnishHost'])) {
            $curlService = GeneralUtility::makeInstance(CurlVarnishService::class);
            assert($curlService instanceof CurlVarnishService);
            $curlService->banTag($tag);
        }
    }

    /**
     * Modify tag to be a regular expression for varnish. This converts a tag for a record (table_uid) to a regex
     * compatible to compressed tag output of EXT:nxcachetags
     */
    protected function modifyTag(string $tag): string
    {
        if ($tag === '.*') {
            return $tag;
        }
        if (preg_match('/^([a-z0-9_]+)_(\d+)$/i', $tag, $matches) === 1) {
            $table = $matches[1];
            $uid = $matches[2];
            $tag = $table . '{[0-9,]*,' . $uid . ',[0-9,]*}';
        } else {
            $tag = preg_quote($tag);
        }

        return ';' . $tag . ';';
    }

    /**
     * According to interface, should do garbage collection. Does nothing.
     */
    public function collectGarbage(): void
    {
    }

    /**
     * Removes all cache entries of this cache which are tagged by the specified tag.
     *
     * @param string $tag The tag the entries must have
     */
    public function flushByTag($tag): void
    {
        $this->flushVarnishByTag($tag);
    }

    /**
     * Removes all cache entries of this cache which are tagged by any of the specified tags.
     *
     * @param string[] $tags List of tags
     */
    public function flushByTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->flushVarnishByTag($tag);
        }
    }

    /**
     * According to interface, should find and returns all cache entry identifiers which are tagged by the
     * specified tag. Does nothing.
     *
     * @param string $tag The tag to search for
     * @return array An array with identifiers of all matching entries. An empty array if no entries matched
     */
    public function findIdentifiersByTag($tag): array
    {
        return [];
    }

}
