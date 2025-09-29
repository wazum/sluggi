<?php

// phpcs:disable PSR1.Files.SideEffects

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Wazum\Sluggi\Backend\SlugModifier;
use Wazum\Sluggi\Helper\Configuration;

defined('TYPO3') || exit;

(static function (): void {
    if (!isset($GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['replacements']['/'])
        && Configuration::get('slash_replacement')) {
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
                'renderType' => 'checkboxLabeledToggle',
                'items' => [
                    [
                        'label' => '',
                        'labelChecked' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.slug_locked.enabled',
                        'labelUnchecked' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.slug_locked.disabled',
                    ],
                ],
            ],
        ],
    ];
    $showItems = ['--linebreak--', 'slug_locked'];

    if (Configuration::get('synchronize')) {
        try {
            $pagesFieldsForSlug = json_decode(
                (string) Configuration::get('pages_fields'),
                true,
                3,
                JSON_THROW_ON_ERROR
            );
            if (!empty($pagesFieldsForSlug)) {
                $GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['fields'] = $pagesFieldsForSlug;
            }
        } catch (Throwable $e) {
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
                'renderType' => 'checkboxLabeledToggle',
                'items' => [
                    [
                        'label' => '',
                        'labelChecked' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.tx_sluggi_sync.enabled',
                        'labelUnchecked' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.tx_sluggi_sync.disabled',
                    ],
                ],
                'default' => 1,
            ],
        ];
        $showItems[] = 'tx_sluggi_sync';
    }

    ExtensionManagementUtility::addTCAcolumns('pages', $fields);
    ExtensionManagementUtility::addFieldsToPalette(
        'pages',
        'title',
        implode(',', $showItems),
        'after:slug'
    );
    ExtensionManagementUtility::addFieldsToPalette(
        'pages',
        'titleonly',
        implode(',', $showItems),
        'after:slug'
    );

    if (ExtensionManagementUtility::isLoaded('masi')) {
        foreach ($GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['postModifiers'] as $key => $modifier) {
            if (B13\Masi\SlugModifier::class . '->modifyGeneratedSlugForPage' === $modifier) {
                $GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['postModifiers'][$key]
                    = SlugModifier::class . '->modifyGeneratedSlugForPage';
                break;
            }
        }

        // Makes no sense and only problems
        unset($GLOBALS['TCA']['pages']['columns']['exclude_slug_for_subpages']['config']['behaviour']['allowLanguageSynchronization']);
        $GLOBALS['TCA']['pages']['columns']['exclude_slug_for_subpages']['l10n_mode'] = 'exclude';
    }
})();
