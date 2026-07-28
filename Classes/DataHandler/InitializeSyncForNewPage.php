<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Utility\DataHandlerUtility;

final class InitializeSyncForNewPage
{
    /**
     * @var array<string, int>
     */
    private array $seededRecords = [];

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_preProcessFieldArray(
        array &$fieldArray,
        string $table,
        string|int $id,
        DataHandler $dataHandler,
    ): void {
        if (!DataHandlerUtility::isNewRecord($id)) {
            return;
        }

        if ($this->applySyncDefault($fieldArray, $table)) {
            $this->seededRecords[$table . ':' . $id] = (int)$fieldArray['tx_sluggi_sync'];
        }
    }

    /**
     * Core applies TCAdefaults only to fields in a showitem, so this column needs it
     * applied here — after fillInFieldArray() dropped it for restricted editors.
     *
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_postProcessFieldArray(
        string $status,
        string $table,
        string|int $id,
        array &$fieldArray,
        DataHandler $dataHandler,
    ): void {
        if ($status !== 'new' || $table !== 'pages') {
            return;
        }

        $seededValue = $this->seededRecords[$table . ':' . $id] ?? null;
        unset($this->seededRecords[$table . ':' . $id]);

        $currentValue = $fieldArray['tx_sluggi_sync'] ?? null;
        // Never overwrite what another hook set, only our own seed.
        $isReplaceable = $currentValue === null || (int)$currentValue === $seededValue;
        $tsConfigDefault = DataHandlerUtility::tcaDefaultValue($dataHandler, $table, 'tx_sluggi_sync');

        if ($tsConfigDefault !== null && $isReplaceable) {
            $fieldArray['tx_sluggi_sync'] = $tsConfigDefault;

            return;
        }

        $this->applySyncDefault($fieldArray, $table);
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    private function applySyncDefault(array &$fieldArray, string $table): bool
    {
        if (array_key_exists('tx_sluggi_sync', $fieldArray)) {
            return false;
        }

        if ($table !== 'pages') {
            return false;
        }

        if (!$this->extensionConfiguration->isSyncEnabled() || !$this->extensionConfiguration->isSyncDefaultEnabled()) {
            return false;
        }

        $fieldArray['tx_sluggi_sync'] = 1;

        return true;
    }
}
