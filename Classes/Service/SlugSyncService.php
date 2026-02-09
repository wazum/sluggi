<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;

final readonly class SlugSyncService
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        private SlugConfigurationService $slugConfigurationService,
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
    public function shouldShowSourceBadge(string $command, array $record): bool
    {
        if ($command === 'new') {
            return true;
        }

        return $this->shouldSync($record);
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
