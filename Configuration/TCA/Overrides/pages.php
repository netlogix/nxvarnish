<?php

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

call_user_func(function ($extensionKey, $table) {
    $tempColumns = [
        'tx_nxvarnish_no_ext_cache' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:nxvarnish/Resources/Private/Language/locallang_db.xlf:pages.tx_nxvarnish_no_ext_cache',
            'config' => [
                'type' => 'check',
                'items' => [
                    '1' => [
                        '0' => 'LLL:EXT:nxvarnish/Resources/Private/Language/locallang_db.xlf:pages.tx_nxvarnish_no_ext_cache_1_formlabel',
                    ],
                ],
            ],
        ],
    ];
    ExtensionManagementUtility::addTCAcolumns('pages', $tempColumns);
    ExtensionManagementUtility::addFieldsToPalette(
        'pages',
        'caching',
        'tx_nxvarnish_no_ext_cache;LLL:EXT:nxvarnish/Resources/Private/Language/locallang_db.xlf:pages.tx_nxvarnish_no_ext_cache_formlabel'
    );
}, 'nxvarnish', 'pages');
