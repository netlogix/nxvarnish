<?php

use Netlogix\Nxvarnish\Cache\Backend\VarnishBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

call_user_func(function () {
    // a dummy cache that is used to promote cache clearing actions to varnish.
    // this is used instead of a clearCachePostProc hook to get notified of Install Tool `Flush Cache` action as well
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_nxvarnish_dummy'] = [
        'frontend' => VariableFrontend::class,
        'backend' => VarnishBackend::class,
        'groups' => ['pages', 'all'],
    ];
});
