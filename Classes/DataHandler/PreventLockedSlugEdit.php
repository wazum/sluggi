<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Service\SlugLockService;

final readonly class PreventLockedSlugEdit
{
    public function __construct(
        private SlugLockService $lockService,
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

        if (!isset($fieldArray['slug'])) {
            return;
        }

        if (!is_numeric($id)) {
            return;
        }

        $record = BackendUtility::getRecordWSOL($table, (int)$id);
        if ($record === null) {
            return;
        }

        if ($this->isBeingUnlocked($fieldArray)) {
            return;
        }

        if ($this->lockService->isLocked($record)) {
            unset($fieldArray['slug']);
        }
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    private function isBeingUnlocked(array $fieldArray): bool
    {
        return array_key_exists('slug_locked', $fieldArray)
            && !$fieldArray['slug_locked'];
    }
}
