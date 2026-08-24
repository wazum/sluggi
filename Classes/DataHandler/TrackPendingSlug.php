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
            // A written slug — whether generated for a pending translation or set by hand by a
            // user allowed to touch the lock — is the confirmed one from now on.
            if (isset($fieldArray['slug'])) {
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

        // Whether the pointer survives fillInFieldArray() depends on the TYPO3 version and the
        // editor's field permissions; the datamap is what localize() writes in every version.
        return DataHandlerUtility::integerFieldValue($dataHandler->datamap['pages'][$id] ?? [], 'l10n_parent');
    }
}
