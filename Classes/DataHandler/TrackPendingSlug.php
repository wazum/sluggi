<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Utility\DataHandlerUtility;

final readonly class TrackPendingSlug
{
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

        if ($status === 'update') {
            // A move or cascade writes a slug too, but nobody confirmed that path.
            if (isset($fieldArray['slug'])
                && !DataHandlerUtility::isRelocationInducedSlugUpdate($dataHandler)
                && !DataHandlerUtility::isNestedSlugUpdate($dataHandler)
            ) {
                $fieldArray['tx_sluggi_slug_pending'] = 0;
            }

            return;
        }

        if ($status !== 'new') {
            return;
        }

        if ($this->translationParentId($fieldArray, $id, $dataHandler) === 0) {
            return;
        }

        $fieldArray['tx_sluggi_slug_pending'] = 1;
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    private function translationParentId(array $fieldArray, string|int $id, DataHandler $dataHandler): int
    {
        $fromFieldArray = DataHandlerUtility::integerFieldValue($fieldArray, 'l10n_parent');
        if ($fromFieldArray > 0) {
            return $fromFieldArray;
        }

        // The field array does not always keep the pointer, the datamap always has it.
        return DataHandlerUtility::integerFieldValue($dataHandler->datamap['pages'][$id] ?? [], 'l10n_parent');
    }
}
