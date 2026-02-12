<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Redirects\RedirectUpdate\SlugRedirectChangeItemFactory;
use TYPO3\CMS\Redirects\Service\SlugService;
use Wazum\Sluggi\Service\SlugGeneratorService;
use Wazum\Sluggi\Service\SlugLockService;
use Wazum\Sluggi\Service\SlugSyncService;
use Wazum\Sluggi\Utility\DataHandlerUtility;

final readonly class HandlePageUpdate
{
    public function __construct(
        private SlugSyncService $syncService,
        private SlugLockService $lockService,
        private SlugGeneratorService $generatorService,
        private SlugRedirectChangeItemFactory $changeItemFactory,
        private SlugService $slugService,
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
        if ($status !== 'update' || $table !== 'pages') {
            return;
        }

        if (DataHandlerUtility::isNestedSlugUpdate($dataHandler)) {
            return;
        }

        $record = BackendUtility::getRecordWSOL($table, (int)$id);
        if ($record === null) {
            return;
        }

        if ($this->lockService->isLocked($record)) {
            return;
        }

        if (!$this->syncService->shouldSync($record)) {
            return;
        }

        if (!$this->syncService->hasSourceFieldChanged($table, $fieldArray)) {
            return;
        }

        $merged = array_merge($record, $fieldArray);

        if (!$this->syncService->hasNonEmptySourceFieldValue($table, $merged)) {
            return;
        }

        $languageId = (int)($record['sys_language_uid'] ?? 0);
        $parentSlug = $this->generatorService->getParentSlug((int)$record['pid'], $languageId);
        $generatedSlug = $this->generatorService->generate($merged, (int)$record['pid']);
        $newSlug = $this->generatorService->combineWithParent(
            $parentSlug,
            $generatedSlug,
            $merged,
            (int)$record['pid'],
        );
        $fieldArray['slug'] = $newSlug;

        if ($newSlug !== $record['slug'] && !$this->coreWillCascadeChildSlugs($dataHandler, (int)$id)) {
            $this->rebuildChildSlugs((int)$id, $record, $fieldArray, $dataHandler);
        }
    }

    /**
     * When the slug was explicitly submitted (browser sends both title and slug),
     * the core's DataHandlerSlugUpdateHook will cascade child slug updates in
     * afterDatabaseOperations. Sluggi must not also cascade, or children get
     * a duplicated path segment.
     */
    private function coreWillCascadeChildSlugs(DataHandler $dataHandler, int $pageId): bool
    {
        return isset($dataHandler->datamap['pages'][$pageId]['slug']);
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $fieldArray
     */
    private function rebuildChildSlugs(int $pageId, array $record, array $fieldArray, DataHandler $dataHandler): void
    {
        $changeItem = $this->changeItemFactory->create($pageId);
        if ($changeItem === null) {
            return;
        }

        $correlationId = $dataHandler->getCorrelationId();
        if ($correlationId === null) {
            return;
        }

        $changeItem = $changeItem->withChanged(array_merge($record, $fieldArray));
        $this->slugService->rebuildSlugsForSlugChange($pageId, $changeItem, $correlationId);
    }
}
