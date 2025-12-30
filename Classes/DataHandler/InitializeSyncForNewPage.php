<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;

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
        if ($table !== 'pages') {
            return;
        }

        if (!$this->isNewRecord($id)) {
            return;
        }

        if (array_key_exists('tx_sluggi_sync', $fieldArray)) {
            return;
        }

        if (!$this->extensionConfiguration->isSyncEnabled()) {
            return;
        }

        if (!$this->extensionConfiguration->isSyncDefaultEnabled()) {
            return;
        }

        $fieldArray['tx_sluggi_sync'] = 1;
    }

    private function isNewRecord(string|int $id): bool
    {
        return is_string($id) && str_starts_with($id, 'NEW');
    }
}
