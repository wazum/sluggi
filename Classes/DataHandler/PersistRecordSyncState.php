<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\SlugSyncService;
use Wazum\Sluggi\Utility\DataHandlerUtility;

final class PersistRecordSyncState
{
    /**
     * @var array<string, bool>
     */
    private array $pendingNewRecords = [];

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly SlugSyncService $syncService,
    ) {
    }

    /**
     * Strip tx_sluggi_sync from fieldArray (column doesn't exist in non-page tables)
     * and stage the value for deferred persistence in afterDatabaseOperations.
     *
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_preProcessFieldArray(
        array &$fieldArray,
        string $table,
        string|int $id,
        DataHandler $dataHandler,
    ): void {
        if ($table === 'pages') {
            return;
        }

        if (!$this->extensionConfiguration->isTableSynchronizeEnabled($table)) {
            return;
        }

        if (!array_key_exists('tx_sluggi_sync', $fieldArray)) {
            return;
        }

        $synced = (bool)$fieldArray['tx_sluggi_sync'];
        unset($fieldArray['tx_sluggi_sync']);

        if (!$this->hasFieldPermission($table)) {
            return;
        }

        if (DataHandlerUtility::isNewRecord($id)) {
            $this->pendingNewRecords[$table . ':' . $id] = $synced;

            return;
        }

        // Stage as pending so HandleRecordUpdate can see the new state,
        // but don't persist to DB yet — that happens in afterDatabaseOperations
        // only if the record update actually succeeds.
        $this->syncService->setPendingSyncState($table, (int)$id, $synced);
    }

    /**
     * Persist sync state to the reference table only after DataHandler
     * has validated permissions and successfully written the record.
     *
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        string|int $id,
        array $fieldArray,
        DataHandler $dataHandler,
    ): void {
        match ($status) {
            'update' => $this->syncService->flushPendingSyncState($table, (int)$id),
            'new' => $this->flushNewRecord($table, $id, $dataHandler),
            default => null,
        };
    }

    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        $this->syncService->clearPendingOverrides();
        $this->pendingNewRecords = [];
    }

    private function flushNewRecord(string $table, string|int $id, DataHandler $dataHandler): void
    {
        $key = $table . ':' . $id;
        if (!isset($this->pendingNewRecords[$key])) {
            return;
        }

        $synced = $this->pendingNewRecords[$key];
        unset($this->pendingNewRecords[$key]);

        $actualUid = (int)($dataHandler->substNEWwithIDs[$id] ?? 0);
        if ($actualUid <= 0) {
            return;
        }

        $this->syncService->setRecordSyncState($table, $actualUid, $synced);
    }

    private function hasFieldPermission(string $table): bool
    {
        if (!($GLOBALS['TCA'][$table]['columns']['tx_sluggi_sync']['exclude'] ?? false)) {
            return true;
        }

        return $this->getBackendUser()->check('non_exclude_fields', $table . ':tx_sluggi_sync');
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
