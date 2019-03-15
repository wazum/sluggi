<?php

defined('TYPO3_MODE') or die ('Access denied.');

if (!isset($GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['replacements']['/'])) {
    // Replace / in slugs with -
    $GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['replacements']['/'] = '-';
}

// Add fallback chain for slug generation
$GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['fields'] = [
    [
        'nav_title',
        'title'
    ]
];

$fields = [
    'tx_sluggi_locked' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang_db.xlf:pages.tx_sluggi_locked',
        'description' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang_db.xlf:pages.tx_sluggi_locked.description',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'items' => [
                [
                    0 => '',
                    1 => '',
                    'labelChecked' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang_db.xlf:pages.tx_sluggi_locked.enabled',
                    'labelUnchecked' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang_db.xlf:pages.tx_sluggi_locked.disabled'
                ]
            ]
        ]
    ]
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', $fields);
foreach ($GLOBALS['TCA']['pages']['palettes'] as &$palette) {
    $palette['showitem'] = str_replace('slug, --linebreak--,',
        'slug, --linebreak--, tx_sluggi_locked, --linebreak--,',
        $palette['showitem']);
}

