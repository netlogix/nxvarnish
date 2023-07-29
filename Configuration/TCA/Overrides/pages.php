<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

call_user_func(function ($extensionKey, $table) {
    $tempColumns = [
        'tx_nxvarnish_no_ext_cache' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:nxvarnish/Resources/Private/Language/locallang_db.xlf:pages.tx_nxvarnish_no_ext_cache',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
                'items' => [
                    [
                        'label' => 'LLL:EXT:nxvarnish/Resources/Private/Language/locallang_db.xlf:pages.tx_nxvarnish_no_ext_cache_1_formlabel',
                        'labelChecked' => 'Enabled',
                        'labelUnchecked' => 'Disabled',
                    ],
                ]
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
