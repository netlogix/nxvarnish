<?php

namespace Netlogix\Nxvarnish\Cache\Backend;

use TYPO3\CMS\Core\Cache\Backend\TaggableBackendInterface;

interface RetrievableTagsBackendInterface extends TaggableBackendInterface
{

    /**
     * Returns all cache tags being stored for this particular identifier.
     *
     * @param string $entryIdentifier
     * @return string[]
     */
    public function getTags(string $entryIdentifier): array;

}
