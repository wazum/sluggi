<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;

final readonly class ClearSlugForExcludedDoktypes
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
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
        if ($table !== 'pages') {
            return;
        }

        $pageType = $this->getPageType($status, $id, $fieldArray);
        if ($this->extensionConfiguration->isPageTypeExcluded($pageType)) {
            $fieldArray['slug'] = '';
            $fieldArray['tx_sluggi_sync'] = 0;
        }
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    private function getPageType(string $status, string|int $id, array $fieldArray): int
    {
        if (isset($fieldArray['doktype'])) {
            return (int)$fieldArray['doktype'];
        }

        if ($status === 'update' && is_numeric($id)) {
            $record = BackendUtility::getRecord('pages', (int)$id, 'doktype');

            return (int)($record['doktype'] ?? 1);
        }

        return 1;
    }
}
