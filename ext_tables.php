<?php

use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

call_user_func(function ($extKey) {
    $esiDoktype = 198;

    // Add new page type:
    $GLOBALS['PAGES_TYPES'][$esiDoktype] = [
        'type' => 'web',
        'allowedTables' => '*',
    ];

    // Provide icon for page tree, list view, ... :
    GeneralUtility::makeInstance(IconRegistry::class)
        ->registerIcon(
            'apps-pagetree-esi',
            TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
            [
                'source' => 'EXT:' . $extKey . '/Resources/Public/Icons/Esi.svg',
            ]
        );

    // Allow backend users to drag and drop the new page type:
    ExtensionManagementUtility::addUserTSConfig(
        'options.pageTree.doktypesToShowInNewPageDragArea := addToList(' . $esiDoktype . ')'
    );
}, 'nxvarnish');
