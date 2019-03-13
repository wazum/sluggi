<?php

defined('TYPO3_MODE') or die ('Access denied.');

if (!isset($GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['replacements']['/'])) {
    // Replace / in slugs with -
    $GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['replacements']['/'] = '-';
}

// Add fallback chain for slug generation
$GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['fields'] = [
    [
        'tx_sluggi_segment',
        'nav_title',
        'title'
    ]
];

$fields = [
    'tx_sluggi_segment' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang_db.xlf:pages.tx_sluggi_segment',
        'description' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang_db.xlf:pages.tx_sluggi_segment.description',
        'config' => [
            'type' => 'input',
            'default' => '',
            'eval' => 'trim,uniqueInPid',
            'max' => '100',
            'size' => '20'
        ]
    ]
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', $fields);
foreach (['title', 'titleonly'] as $palette) {
    $GLOBALS['TCA']['pages']['palettes'][$palette]['showitem'] = str_replace('slug, --linebreak--,', 'slug, --linebreak--, tx_sluggi_segment, --linebreak--,', $GLOBALS['TCA']['pages']['palettes'][$palette]['showitem']);
}

