<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Service\FullPathEditingService;
use Wazum\Sluggi\Service\SlugGeneratorService;
use Wazum\Sluggi\Utility\DataHandlerUtility;
use Wazum\Sluggi\Utility\SlugUtility;

final class LockSlugOnFullPathEdit
{
    /**
     * @var array<string, bool>
     */
    private array $pendingLocks = [];

    public function __construct(
        private readonly FullPathEditingService $fullPathEditingService,
        private readonly SlugGeneratorService $slugGeneratorService,
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

        if (DataHandlerUtility::isNewRecord($id)) {
            return;
        }

        if (!$this->fullPathEditingService->isAllowedForRequest($fieldArray, $table)) {
            return;
        }

        $record = BackendUtility::getRecordWSOL('pages', (int)$id, 'slug,pid,sys_language_uid');
        if ($record === null) {
            return;
        }

        $oldSlug = (string)$record['slug'];
        $newSlug = (string)$fieldArray['slug'];

        if ($oldSlug === $newSlug) {
            return;
        }

        $parentSlug = $this->slugGeneratorService->getParentSlug(
            (int)$record['pid'],
            (int)($record['sys_language_uid'] ?? 0)
        );

        if (SlugUtility::slugMatchesHierarchy($newSlug, $parentSlug)) {
            return;
        }

        $fieldArray['slug_locked'] = 1;
        $this->pendingLocks[$table . ':' . $id] = true;
    }

    /**
     * Re-applies the lock after fillInFieldArray() dropped slug_locked for users
     * without non_exclude_fields permission. The tx_sluggi_full_path flag the
     * decision depends on is gone by then, so it is staged above instead.
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
        $key = $table . ':' . $id;
        if (!isset($this->pendingLocks[$key])) {
            return;
        }

        unset($this->pendingLocks[$key]);
        $fieldArray['slug_locked'] = 1;
    }
}
