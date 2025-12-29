<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Service\SlugConfigurationService;

$synchronizeTables = [];

try {
    $value = GeneralUtility::makeInstance(ExtensionConfiguration::class)
        ->get('sluggi', 'synchronize_tables');
    if (is_string($value) && $value !== '') {
        $synchronizeTables = array_values(array_filter(array_map(trim(...), explode(',', $value))));
    }
} catch (Exception) {
}

$slugConfigurationService = new SlugConfigurationService();

foreach ($synchronizeTables as $table) {
    if (!isset($GLOBALS['TCA'][$table])) {
        continue;
    }

    $slugFieldName = $slugConfigurationService->getSlugFieldName($table);
    if ($slugFieldName === null) {
        continue;
    }

    $GLOBALS['TCA'][$table]['columns'][$slugFieldName]['config']['renderType'] = 'sluggiSlug';

    foreach ($slugConfigurationService->getRequiredSourceFields($table) as $fieldName) {
        if (isset($GLOBALS['TCA'][$table]['columns'][$fieldName]['config'])) {
            $GLOBALS['TCA'][$table]['columns'][$fieldName]['config']['renderType'] = 'slugSourceInput';
        }
    }
}
