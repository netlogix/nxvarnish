<?php

use Netlogix\Nxvarnish\Cache\Backend\RetrievableTagsProxyBackend;
use Netlogix\Nxvarnish\Cache\Backend\Typo3DatabaseBackend;
use Netlogix\Nxvarnish\Cache\VarnishBackend;
use Netlogix\Nxvarnish\Hooks\TypoScriptFrontendController;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3_MODE') or die();

call_user_func(function () {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_nxvarnish_dummy'] = [
        'frontend' => VariableFrontend::class,
        'backend' => VarnishBackend::class,
        'groups' => ['pages', 'all']
    ];

    $allowCacheLogin = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('nxvarnish', 'allowCacheLogin');
    if ($allowCacheLogin) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::class]['className'] = TypoScriptFrontendController::class;
    }

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cache_pages'] = [
        'frontend' => VariableFrontend::class,
        'backend' => RetrievableTagsProxyBackend::class,
        'options' => [],
        'groups' => ['pages', 'all']
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cache_pages_nxvarnish_proxy'] = [
        'frontend' => VariableFrontend::class,
        'backend' => Typo3DatabaseBackend::class,
        'options' => [],
        'groups' => ['pages', 'all']
    ];
});
