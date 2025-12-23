<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use Wazum\Sluggi\Configuration\ExtensionConfiguration;

final readonly class SlugSyncService
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
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
        return $this->isSyncFeatureEnabled()
            && ($record['tx_sluggi_sync'] ?? false);
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
        $configService = new SlugConfigurationService();
        $sourceFields = $configService->getSourceFields($table);

        return array_intersect($sourceFields, array_keys($fieldArray)) !== [];
    }

    /**
     * @param array<string, mixed> $record
     */
    public function hasNonEmptySourceFieldValue(string $table, array $record): bool
    {
        $configService = new SlugConfigurationService();
        $sourceFields = $configService->getSourceFields($table);

        foreach ($sourceFields as $field) {
            $value = $record[$field] ?? '';
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }
}
