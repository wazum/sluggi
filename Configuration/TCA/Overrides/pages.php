<?php

declare(strict_types=1);

use Wazum\Sluggi\Service\SlugConfigurationService;

$GLOBALS['TCA']['pages']['columns']['slug']['config']['renderType'] = 'sluggiSlug';

$GLOBALS['TCA']['pages']['columns']['tx_sluggi_sync'] = [
    'config' => [
        'type' => 'passthrough',
    ],
];

foreach ((new SlugConfigurationService())->getRequiredSourceFields('pages') as $fieldName) {
    if (isset($GLOBALS['TCA']['pages']['columns'][$fieldName]['config'])) {
        $GLOBALS['TCA']['pages']['columns'][$fieldName]['config']['renderType'] = 'slugSourceInput';
    }
}
