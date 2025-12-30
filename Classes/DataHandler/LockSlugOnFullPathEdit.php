<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Service\FullPathEditingService;

final readonly class LockSlugOnFullPathEdit
{
    public function __construct(
        private FullPathEditingService $fullPathEditingService,
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
        if ($table !== 'pages' || !isset($fieldArray['slug'])) {
            return;
        }

        if (!is_int($id) && !ctype_digit($id)) {
            return;
        }

        if (!$this->fullPathEditingService->isAllowedForRequest($fieldArray, $table)) {
            return;
        }

        $record = BackendUtility::getRecordWSOL('pages', (int)$id, 'slug');
        if ($record === null) {
            return;
        }

        $oldSlug = (string)$record['slug'];
        $newSlug = (string)$fieldArray['slug'];

        if ($oldSlug !== $newSlug) {
            $fieldArray['slug_locked'] = 1;
        }
    }
}
