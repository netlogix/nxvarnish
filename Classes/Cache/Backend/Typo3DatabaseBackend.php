<?php

namespace Netlogix\Nxvarnish\Cache\Backend;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

class Typo3DatabaseBackend extends \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend
{
    public function setCache(FrontendInterface $cache)
    {
        parent::setCache($cache);
        $this->cacheTable = str_replace('_nxvarnish_proxy', '', $this->cacheTable);
        $this->tagsTable = str_replace('_nxvarnish_proxy', '', $this->tagsTable);
    }
}
