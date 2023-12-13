<?php

// phpcs:disable PSR1.Files.SideEffects
defined('TYPO3') || exit;

if (!function_exists('array_flatten')) {
    /**
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    function array_flatten(array $array): array
    {
        $merged = [[]];
        foreach ($array as $value) {
            if (is_array($value)) {
                $merged[] = array_flatten($value);
            } else {
                $merged[] = [$value];
            }
        }

        return array_merge([], ...$merged);
    }
}

(static function (): void {
    if (!isset($GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['replacements']['/'])
        && \Wazum\Sluggi\Helper\Configuration::get('slash_replacement')) {
        // Replace / in slugs with - by default
        $GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['replacements']['/'] = '-';
    }

    $fields = [
        'slug_locked' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.slug_locked',
            'description' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.slug_locked.description',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        0 => '',
                        1 => '',
                        'labelChecked' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.tx_sluggi_lock.enabled',
                        'labelUnchecked' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.tx_sluggi_lock.disabled',
                    ],
                ],
            ],
        ],
    ];
    $showItems = ['--linebreak--', 'slug_locked'];

    if (\Wazum\Sluggi\Helper\Configuration::get('synchronize')) {
        try {
            $pagesFieldsForSlug = json_decode(
                (string) \Wazum\Sluggi\Helper\Configuration::get('pages_fields'),
                true,
                3,
                0
            );
            if (!empty($pagesFieldsForSlug)) {
                $GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['fields'] = $pagesFieldsForSlug;
            }
        } catch (\Throwable $e) {
            // Use default fallback
            $pagesFieldsForSlug = [
                [
                    'nav_title',
                    'title',
                ],
            ];
            $GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['fields'] = $pagesFieldsForSlug;
        }

        foreach (array_flatten($pagesFieldsForSlug ?? []) as $field) {
            if (isset($GLOBALS['TCA']['pages']['columns'][$field]['config'])) {
                $GLOBALS['TCA']['pages']['columns'][$field]['config']['renderType'] = 'inputTextWithSlugImpact';
            }
        }

        $fields['tx_sluggi_sync'] = [
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
                        'labelUnchecked' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.tx_sluggi_sync.disabled',
                    ],
                ],
                'default' => 1,
            ],
        ];
        $showItems[] = 'tx_sluggi_sync';
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

    if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('masi')) {
        foreach ($GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['postModifiers'] as $key => $modifier) {
            if (\B13\Masi\SlugModifier::class . '->modifyGeneratedSlugForPage' === $modifier) {
                $GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['postModifiers'][$key]
                    = \Wazum\Sluggi\Backend\SlugModifier::class . '->modifyGeneratedSlugForPage';
                break;
            }
        }

        // Makes no sense and only problems
        unset($GLOBALS['TCA']['pages']['columns']['exclude_slug_for_subpages']['config']['behaviour']['allowLanguageSynchronization']);
        $GLOBALS['TCA']['pages']['columns']['exclude_slug_for_subpages']['l10n_mode'] = 'exclude';
    }
})();
