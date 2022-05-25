<?php

use Netlogix\Nxvarnish\Cache\Backend\VarnishBackend;
use Netlogix\Nxvarnish\Hooks\ContentPostProcHook;
use Netlogix\Nxvarnish\Xclass\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController as T3TypoScriptFrontendController;

defined('TYPO3_MODE') or die();

call_user_func(function () {
    // a dummy cache that is used to promote cache clearing actions to varnish.
    // this is used instead of a clearCachePostProc hook to get notified of Install Tool `Flush Cache` action as well
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_nxvarnish_dummy'] = [
        'frontend' => VariableFrontend::class,
        'backend' => VarnishBackend::class,
        'groups' => ['pages', 'all']
    ];

    $allowCacheLogin = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('nxvarnish', 'allowCacheLogin');
    if ($allowCacheLogin) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][T3TypoScriptFrontendController::class]['className'] = TypoScriptFrontendController::class;
    }

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-cached'][] =
        ContentPostProcHook::class . '->cached';
});
