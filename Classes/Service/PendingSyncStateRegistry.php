<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Holds in-flight sync-toggle values across DataHandler hooks within a single request.
 *
 * Kept as a shared service so `PersistRecordSyncState` and `HandleRecordUpdate` see
 * the same staged value during one save. Scoped by DataHandler object hash so a
 * nested DataHandler's afterAllOperations only clears its own entries, not the
 * outer flow's staged state.
 */
final class PendingSyncStateRegistry
{
    /**
     * @var array<string, array<string, bool>>
     */
    private array $scopes = [];

    public function set(DataHandler $dataHandler, string $table, int $uid, bool $synced): void
    {
        $this->scopes[$this->scopeKey($dataHandler)][$this->entryKey($table, $uid)] = $synced;
    }

    public function get(string $table, int $uid): ?bool
    {
        $key = $this->entryKey($table, $uid);
        foreach ($this->scopes as $scope) {
            if (array_key_exists($key, $scope)) {
                return $scope[$key];
            }
        }

        return null;
    }

    public function consume(DataHandler $dataHandler, string $table, int $uid): ?bool
    {
        $scopeKey = $this->scopeKey($dataHandler);
        $entryKey = $this->entryKey($table, $uid);
        $value = $this->scopes[$scopeKey][$entryKey] ?? null;
        unset($this->scopes[$scopeKey][$entryKey]);

        return $value;
    }

    public function clearScope(DataHandler $dataHandler): void
    {
        unset($this->scopes[$this->scopeKey($dataHandler)]);
    }

    private function scopeKey(DataHandler $dataHandler): string
    {
        return spl_object_hash($dataHandler);
    }

    private function entryKey(string $table, int $uid): string
    {
        return $table . ':' . $uid;
    }
}
