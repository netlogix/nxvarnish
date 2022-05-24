<?php

defined('TYPO3_MODE') or die();

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


    $esiPageIcon = PathUtility::stripPathSitePrefix(
        ExtensionManagementUtility::extPath($extensionKey, 'Resources/Public/Icons/Esi.svg')
    );
    $esiDoktype = 198;

    // Add new page type as possible select item:
    ExtensionManagementUtility::addTcaSelectItem(
        $table,
        'doktype',
        [
            'LLL:EXT:' . $extensionKey . '/Resources/Private/Language/locallang_db.xlf:esi_page_type',
            $esiDoktype,
            $esiPageIcon
        ],
        '1',
        'after'
    );

    // Add icon for new page type:
    ArrayUtility::mergeRecursiveWithOverrule(
        $GLOBALS['TCA'][$table],
        [
            'ctrl' => [
                'typeicon_classes' => [
                    $esiDoktype => 'apps-pagetree-esi',
                ],
            ],
            'types' => [
                (string)$esiDoktype => [
                    'showitem' => '
                            --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.palettes.external;external,
                            --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.palettes.title;title,
                            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.access,
                            --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.palettes.visibility;visibility,
                            --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.palettes.access;access,
                            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.behaviour,
                            --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.palettes.links;links,
                            --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.palettes.language;language,
                            --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.palettes.miscellaneous;miscellaneous,
                            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.extended,
                        '
                ]
            ],
        ]
    );
}, 'nxvarnish', 'pages');
