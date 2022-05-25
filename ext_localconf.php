<?php

use Netlogix\Nxvarnish\Hooks\TypoScriptFrontendController;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3_MODE') or die();

call_user_func(function () {
    // a dummy cache that is used to promote cache clearing actions to varnish.
    // this is used instead of a clearCachePostProc hook to get notified of Install Tool `Flush Cache` action as well
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_nxvarnish_dummy'] = [
        'frontend' => VariableFrontend::class,
        'backend' => \Netlogix\Nxvarnish\Cache\Backend\VarnishBackend::class,
        'groups' => ['pages', 'all']
    ];

    $allowCacheLogin = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('nxvarnish', 'allowCacheLogin');
    if ($allowCacheLogin) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::class]['className'] = TypoScriptFrontendController::class;
    }

});
