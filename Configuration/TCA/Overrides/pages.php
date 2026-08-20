<?php

declare(strict_types=1);

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
