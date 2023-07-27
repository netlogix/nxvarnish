<?php

namespace Netlogix\Nxvarnish\Xclass\Controller;

class TypoScriptFrontendController extends \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
{

    /**
     * We override the default implementation, in order to tell TYPO3 to send Cache control headers even though we have a user logged in
     *
     * @return bool
     */
    function isStaticCacheble(): bool
    {
        $doCache = !$this->no_cache && !$this->isINTincScript() && $this->isExternalCachable();

        return $doCache;
    }

    /**
     * Check whether the current page may be cached by external caches
     *
     * @return bool
     */
    protected function isExternalCachable(): bool
    {
        return !($this->page['tx_nxvarnish_no_ext_cache'] ?? false);
    }

}
