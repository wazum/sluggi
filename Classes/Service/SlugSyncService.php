<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;

final class SlugSyncService
{
    /**
     * @var array<string, bool>
     */
    private array $pendingOverrides = [];

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly SlugConfigurationService $slugConfigurationService,
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    public function isSyncFeatureEnabled(): bool
    {
        return $this->extensionConfiguration->isSyncEnabled();
    }

    /**
     * @param array<string, mixed> $record
     */
    public function shouldSync(array $record): bool
    {
        if (!$this->isSyncFeatureEnabled()) {
            return false;
        }

        $syncValue = $this->getSyncValue($record);

        return (bool)$syncValue;
    }

    /**
     * Get the effective sync value, respecting l10n_mode inheritance for translations.
     *
     * @param array<string, mixed> $record
     */
    public function getSyncValue(array $record): bool
    {
        $languageId = $record['sys_language_uid'] ?? 0;
        $languageId = (int)(is_array($languageId) ? ($languageId[0] ?? 0) : $languageId);

        $l10nParent = $record['l10n_parent'] ?? 0;
        $l10nParent = (int)(is_array($l10nParent) ? ($l10nParent[0] ?? 0) : $l10nParent);

        if ($languageId > 0 && $l10nParent > 0) {
            $parentRecord = BackendUtility::getRecordWSOL('pages', $l10nParent, 'tx_sluggi_sync');
            if ($parentRecord !== null) {
                return (bool)($parentRecord['tx_sluggi_sync'] ?? false);
            }
        }

        return (bool)($record['tx_sluggi_sync'] ?? false);
    }

    public function isTableAutoSyncEnabled(string $table): bool
    {
        return $this->extensionConfiguration->isTableSynchronizeEnabled($table);
    }

    /**
     * @param array<string, mixed> $record
     */
    public function shouldShowSourceBadge(string $table, string $command, array $record): bool
    {
        if ($command === 'new') {
            return true;
        }

        if ($table !== 'pages') {
            return $this->extensionConfiguration->isTableSynchronizeEnabled($table)
                && $this->getEffectiveRecordSyncState($table, $record);
        }

        return $this->shouldSync($record);
    }

    /**
     * @param array<string, mixed> $record
     */
    public function getEffectiveRecordSyncState(string $table, array $record): bool
    {
        $uid = (int)($record['uid'] ?? 0);

        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? '';
        $transOrigPointerField = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] ?? '';

        if ($languageField !== '' && $transOrigPointerField !== '') {
            $languageId = $record[$languageField] ?? 0;
            $languageId = (int)(is_array($languageId) ? ($languageId[0] ?? 0) : $languageId);
            $l10nParent = $record[$transOrigPointerField] ?? 0;
            $l10nParent = (int)(is_array($l10nParent) ? ($l10nParent[0] ?? 0) : $l10nParent);

            if ($languageId > 0 && $l10nParent > 0) {
                return $this->getRecordSyncState($table, $l10nParent);
            }
        }

        return $this->getRecordSyncState($table, $uid);
    }

    public function setPendingSyncState(string $table, int $uid, bool $synced): void
    {
        $this->pendingOverrides[$table . ':' . $uid] = $synced;
    }

    public function flushPendingSyncState(string $table, int $uid): void
    {
        $key = $table . ':' . $uid;
        if (!isset($this->pendingOverrides[$key])) {
            return;
        }

        $this->setRecordSyncState($table, $uid, $this->pendingOverrides[$key]);
        unset($this->pendingOverrides[$key]);
    }

    public function clearPendingOverrides(): void
    {
        $this->pendingOverrides = [];
    }

    public function getRecordSyncState(string $table, int $uid): bool
    {
        if ($uid <= 0) {
            return true;
        }

        $key = $table . ':' . $uid;
        if (isset($this->pendingOverrides[$key])) {
            return $this->pendingOverrides[$key];
        }

        $row = $this->connectionPool
            ->getConnectionForTable('tx_sluggi_record_sync')
            ->select(['is_synced'], 'tx_sluggi_record_sync', [
                'tablename' => $table,
                'record_uid' => $uid,
            ])
            ->fetchAssociative();

        if ($row === false) {
            return true;
        }

        return (bool)$row['is_synced'];
    }

    public function setRecordSyncState(string $table, int $uid, bool $synced): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_sluggi_record_sync');

        $existing = $connection->select(['is_synced'], 'tx_sluggi_record_sync', [
            'tablename' => $table,
            'record_uid' => $uid,
        ])->fetchAssociative();

        if ($existing !== false) {
            $connection->update(
                'tx_sluggi_record_sync',
                ['is_synced' => $synced ? 1 : 0],
                ['tablename' => $table, 'record_uid' => $uid],
            );
        } else {
            $connection->insert('tx_sluggi_record_sync', [
                'tablename' => $table,
                'record_uid' => $uid,
                'is_synced' => $synced ? 1 : 0,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function hasSourceFieldChanged(string $table, array $fieldArray): bool
    {
        $sourceFields = $this->slugConfigurationService->getSourceFields($table);

        return array_intersect($sourceFields, array_keys($fieldArray)) !== [];
    }

    /**
     * @param array<string, mixed> $record
     */
    public function hasNonEmptySourceFieldValue(string $table, array $record): bool
    {
        $sourceFields = $this->slugConfigurationService->getSourceFields($table);

        foreach ($sourceFields as $field) {
            $value = $record[$field] ?? '';
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }
}
