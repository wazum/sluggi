<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\SlugConfigurationService;
use Wazum\Sluggi\Service\SlugGeneratorService;

final readonly class HandleRecordUndelete
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        private SlugConfigurationService $configurationService,
        private SlugGeneratorService $slugGeneratorService,
    ) {
    }

    public function processCmdmap_postProcess(
        string $command,
        string $table,
        string|int $id,
        mixed $value,
        DataHandler $dataHandler,
    ): void {
        if ($command !== 'undelete' || $table === 'pages') {
            return;
        }

        if (!$this->extensionConfiguration->isTableSynchronizeEnabled($table)) {
            return;
        }

        $slugField = $this->configurationService->getSlugFieldName($table);
        if ($slugField === null) {
            return;
        }

        $record = BackendUtility::getRecordWSOL($table, (int)$id);
        if (empty($record)) {
            return;
        }

        $currentSlug = $record[$slugField] ?? '';
        if ($currentSlug === '') {
            return;
        }

        $uniqueSlug = $this->slugGeneratorService->ensureUnique(
            $currentSlug,
            $record,
            (int)$record['pid'],
            (int)$id,
            $table,
            $slugField,
        );

        if ($uniqueSlug !== $currentSlug) {
            $updateDataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $updateDataHandler->start(
                [$table => [(int)$id => [$slugField => $uniqueSlug]]],
                []
            );
            $updateDataHandler->process_datamap();
        }
    }
}
