<?php

declare(strict_types=1);

use Wazum\Sluggi\Service\SlugConfigurationService;
use Wazum\Sluggi\Slug\SluggiAwareSlugModifier;

$GLOBALS['TCA']['pages']['columns']['slug']['config']['renderType'] = 'sluggiSlug';

$GLOBALS['TCA']['pages']['columns']['tx_sluggi_sync'] = [
    'exclude' => true,
    'l10n_mode' => 'exclude',
    'label' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.tx_sluggi_sync',
    'config' => [
        'type' => 'passthrough',
    ],
];

$GLOBALS['TCA']['pages']['columns']['slug_locked'] = [
    'exclude' => true,
    'l10n_mode' => 'exclude',
    'label' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.slug_locked',
    'config' => [
        'type' => 'passthrough',
    ],
];

$GLOBALS['TCA']['pages']['columns']['tx_sluggi_full_path'] = [
    'exclude' => true,
    'label' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:pages.tx_sluggi_full_path',
    'config' => [
        'type' => 'none',
    ],
];

foreach ((new SlugConfigurationService())->getSourceFields('pages') as $fieldName) {
    if (isset($GLOBALS['TCA']['pages']['columns'][$fieldName]['config'])) {
        $GLOBALS['TCA']['pages']['columns'][$fieldName]['config']['renderType'] = 'slugSourceInput';
    }
}

// When b13/masi is installed AND loaded as a TYPO3 extension, replace its
// postModifier with sluggi's subclass. The subclass inherits all of masi's
// behavior (PageTSconfig overrides, per-page flag, prefix, fieldSeparator,
// replacements, language overlay) and additionally honors sluggi's
// exclude_doktypes config in the same parent-skip loop.
// masi remains an optional dependency: this block runs only when masi is
// loaded as an extension, and SluggiAwareSlugModifier (which extends
// B13\Masi\SlugModifier) is only autoloaded under that guard.
// The try/catch covers minimal unit-test environments where the PackageManager
// hasn't been initialized — there the override file is exercised directly.
$masiLoaded = false;
try {
    $masiLoaded = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('masi');
} catch (\Throwable) {
    // PackageManager unavailable — treat as "masi not loaded" and skip integration.
}
if ($masiLoaded) {
    $modifiers = &$GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['postModifiers'];
    $modifiers ??= [];
    foreach ($modifiers as $key => $reference) {
        if (str_contains((string)$reference, 'B13\\Masi\\SlugModifier')) {
            unset($modifiers[$key]);
        }
    }
    $modifiers = array_values($modifiers);
    $modifiers[] = SluggiAwareSlugModifier::class . '->modifyGeneratedSlugForPage';
}
