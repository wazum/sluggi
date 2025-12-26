<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\SlugConfigurationService;
use Wazum\Sluggi\Service\SlugGeneratorService;
use Wazum\Sluggi\Service\SlugSyncService;

final readonly class HandleRecordUpdate
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        private SlugConfigurationService $configurationService,
        private SlugSyncService $syncService,
        private SlugGeneratorService $generatorService,
    ) {
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_postProcessFieldArray(
        string $status,
        string $table,
        string|int $id,
        array &$fieldArray,
        DataHandler $dataHandler,
    ): void {
        if ($status !== 'update' || $table === 'pages') {
            return;
        }

        if (!$this->extensionConfiguration->isTableSynchronizeEnabled($table)) {
            return;
        }

        $slugField = $this->configurationService->getSlugFieldName($table);
        if ($slugField === null) {
            return;
        }

        if (!$this->syncService->hasSourceFieldChanged($table, $fieldArray)) {
            return;
        }

        $record = BackendUtility::getRecordWSOL($table, (int)$id);
        if ($record === null) {
            return;
        }

        $merged = array_merge($record, $fieldArray);

        if (!$this->syncService->hasNonEmptySourceFieldValue($table, $merged)) {
            return;
        }

        $fieldArray[$slugField] = $this->generatorService->generateForTable(
            $merged,
            (int)$record['pid'],
            $table,
            $slugField
        );
    }
}
