<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Utility\DataHandlerUtility;

final readonly class InitializeLockForNewPage
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
        if ($status !== 'new' || $table !== 'pages') {
            return;
        }

        if (array_key_exists('slug_locked', $fieldArray)) {
            return;
        }

        $tsConfigDefault = DataHandlerUtility::tcaDefaultValue($dataHandler, $table, 'slug_locked');
        if ($tsConfigDefault !== null) {
            $fieldArray['slug_locked'] = $tsConfigDefault;
        }
    }
}
