<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\SlugConfigurationService;

final readonly class HandleRecordUndelete
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        private SlugConfigurationService $configurationService,
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

        $record = BackendUtility::getRecord($table, (int)$id);
        if (empty($record)) {
            return;
        }

        $currentSlug = $record[$slugField] ?? '';
        if ($currentSlug === '') {
            return;
        }

        $slugHelper = $this->getSlugHelper($table, $slugField);
        $state = RecordStateFactory::forName($table)->fromArray($record, (int)$record['pid'], (int)$id);
        $uniqueSlug = $this->buildUniqueSlug($slugHelper, $currentSlug, $state, $table, $slugField);

        if ($uniqueSlug !== $currentSlug) {
            $updateDataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $updateDataHandler->start(
                [$table => [(int)$id => [$slugField => $uniqueSlug]]],
                []
            );
            $updateDataHandler->process_datamap();
        }
    }

    private function buildUniqueSlug(
        SlugHelper $slugHelper,
        string $slug,
        \TYPO3\CMS\Core\DataHandling\Model\RecordState $state,
        string $table,
        string $slugField,
    ): string {
        $eval = $GLOBALS['TCA'][$table]['columns'][$slugField]['config']['eval'] ?? '';

        return match ($eval) {
            'unique' => $slugHelper->buildSlugForUniqueInTable($slug, $state),
            'uniqueInPid' => $slugHelper->buildSlugForUniqueInPid($slug, $state),
            default => $slugHelper->buildSlugForUniqueInSite($slug, $state),
        };
    }

    private function getSlugHelper(string $table, string $slugField): SlugHelper
    {
        return GeneralUtility::makeInstance(
            SlugHelper::class,
            $table,
            $slugField,
            $GLOBALS['TCA'][$table]['columns'][$slugField]['config'] ?? []
        );
    }
}
