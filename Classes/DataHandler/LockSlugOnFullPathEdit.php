<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Service\FullPathEditingService;
use Wazum\Sluggi\Service\SlugGeneratorService;
use Wazum\Sluggi\Utility\SlugUtility;

final readonly class LockSlugOnFullPathEdit
{
    public function __construct(
        private FullPathEditingService $fullPathEditingService,
        private SlugGeneratorService $slugGeneratorService,
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
    }
}
