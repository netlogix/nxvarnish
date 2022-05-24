<?php

namespace Netlogix\Nxvarnish\Hooks;

class TypoScriptFrontendController extends \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
{

    /**
     * We override the default implementation, in order to tell TYPO3 to send Cache control headers even though we have a user logged in
     *
     * @return bool
     */
    function isStaticCacheble()
    {
        $doCache = !$this->no_cache && !$this->isINTincScript() && $this->isExternalCachable();

        return $doCache;
    }

    /**
     * Check whether the current page may be cached by external caches
     *
     * @return bool
     */
    protected function isExternalCachable()
    {
        return !$this->page['tx_nxvarnish_no_ext_cache'];
    }

}
