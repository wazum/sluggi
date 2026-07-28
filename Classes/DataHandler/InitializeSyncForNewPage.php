<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Utility\DataHandlerUtility;

final readonly class InitializeSyncForNewPage
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
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

        $this->applySyncDefault($fieldArray, $table);
    }

    /**
     * Re-applies the default after fillInFieldArray() dropped tx_sluggi_sync
     * for users without non_exclude_fields permission.
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
        if ($status !== 'new') {
            return;
        }

        $this->applySyncDefault($fieldArray, $table);
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    private function applySyncDefault(array &$fieldArray, string $table): void
    {
        if (array_key_exists('tx_sluggi_sync', $fieldArray)) {
            return;
        }

        if ($table !== 'pages') {
            return;
        }

        if ($this->extensionConfiguration->isSyncEnabled() && $this->extensionConfiguration->isSyncDefaultEnabled()) {
            $fieldArray['tx_sluggi_sync'] = 1;
        }
    }
}
