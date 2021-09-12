<?php

defined('TYPO3_MODE') or die ('Access denied.');

if (!function_exists('array_flatten')) {
    function array_flatten($array): array {
        $merged = [[]];
        foreach ($array as $value) {
            if (is_array($value)) {
                $merged[] = array_flatten($value);
            }
            else {$merged[] = [$value];}
        }

        return array_merge([], ...$merged);
    }
}

(static function () {
    if (Wazum\Sluggi\Helper\Configuration::get('slash_replacement') &&
        !isset($GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['replacements']['/'])) {
        // Replace / in slugs with -
        $GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['replacements']['/'] = '-';
    }

    try {
        $pagesFieldsForSlug = json_decode(
            (string)Wazum\Sluggi\Helper\Configuration::get('pages_fields'),
            true,
            3,
            JSON_THROW_ON_ERROR
        );
        if (!empty($pagesFieldsForSlug)) {
            $GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['fields'] = $pagesFieldsForSlug;
        }
    } catch (Exception $e) {
        // Use default fallback
        $pagesFieldsForSlug = [
            [
                'nav_title',
                'title'
            ]
        ];
        $GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['fields'] = $pagesFieldsForSlug;
    }

    foreach (array_flatten($pagesFieldsForSlug) as $field) {
        if (isset($GLOBALS['TCA']['pages']['columns'][$field]['config'])) {
            $GLOBALS['TCA']['pages']['columns'][$field]['config']['renderType'] = 'inputTextWithSlugImpact';
        }
    }

    $fields = [
        'tx_sluggi_lock' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.tx_sluggi_lock',
            'description' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.tx_sluggi_lock.description',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        0 => '',
                        1 => '',
                        'labelChecked' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.tx_sluggi_lock.enabled',
                        'labelUnchecked' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.tx_sluggi_lock.disabled'
                    ]
                ]
            ]
        ],
        'tx_sluggi_sync' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.tx_sluggi_sync',
            'description' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.tx_sluggi_sync.description',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        0 => '',
                        1 => '',
                        'labelChecked' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.tx_sluggi_sync.enabled',
                        'labelUnchecked' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.tx_sluggi_sync.disabled'
                    ]
                ],
                'default' => 1
            ]
        ]
    ];
    $showItems = ['--linebreak--', 'tx_sluggi_lock', 'tx_sluggi_sync'];
    if (!(bool)Wazum\Sluggi\Helper\Configuration::get('synchronize')) {
        unset($fields['tx_sluggi_sync'], $showItems['tx_sluggi_sync']);
    }

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', $fields);
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
        'pages',
        'title',
        implode(',', $showItems),
        'after:slug'
    );
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
        'pages',
        'titleonly',
        implode(',', $showItems),
        'after:slug'
    );
})();
