<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Cache\Backend;

use Override;
use Netlogix\Nxvarnish\Service\VarnishService;
use TYPO3\CMS\Core\Cache\Backend\NullBackend;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class VarnishBackend extends NullBackend
{
    /**
     * Removes all cache entries of this cache.
     */
    public function flush(): void
    {
        $this->flushVarnishByTag('.*');
    }

    /**
     * Removes all cache entries of this cache which are tagged by the specified tag.
     *
     * @param string $tag The tag the entries must have
     */
    public function flushByTag($tag): void
    {
        $this->flushVarnishByTag((string) $tag);
    }

    /**
     * Removes all cache entries of this cache which are tagged by any of the specified tags.
     *
     * @param string[] $tags List of tags
     */
    #[Override]
    public function flushByTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->flushVarnishByTag((string) $tag);
        }
    }

    private function flushVarnishByTag(string $tag): void
    {
        $tag = $this->modifyTag($tag);

        $this->getVarnishService()->banTag($tag);
    }

    /**
     * Modify tag to be a regular expression for varnish. This converts a tag for a record (table_uid) to a regex
     * compatible to compressed tag output of EXT:nxcachetags
     */
    private function modifyTag(string $tag): string
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

    private function getVarnishService(): VarnishService
    {
        return GeneralUtility::makeInstance(VarnishService::class);
    }
}
