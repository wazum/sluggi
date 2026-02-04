<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Service\SlugGeneratorService;

final readonly class HandlePageCopy
{
    public function __construct(
        private SlugGeneratorService $slugGeneratorService,
    ) {
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $pasteDataMap
     */
    public function processCmdmap_postProcess(
        string $command,
        string $table,
        string|int $id,
        mixed $value,
        DataHandler $dataHandler,
        mixed $pasteUpdate,
        array &$pasteDataMap,
    ): void {
        if ($command !== 'copy' || $table !== 'pages') {
            return;
        }

        $copiedPages = $dataHandler->copyMappingArray['pages'] ?? [];
        $processedSlugs = [];

        foreach ($copiedPages as $sourceUid => $targetUid) {
            $this->updateSlugForCopiedPage($sourceUid, $targetUid, $pasteDataMap, $copiedPages, $processedSlugs);
        }
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $pasteDataMap
     * @param array<int, int>                                 $copiedPages
     * @param array<int, string>                              $processedSlugs
     */
    private function updateSlugForCopiedPage(
        int $sourceUid,
        int $targetUid,
        array &$pasteDataMap,
        array $copiedPages,
        array &$processedSlugs,
    ): void {
        $sourcePage = BackendUtility::getRecordWSOL('pages', $sourceUid);
        if (empty($sourcePage)) {
            return;
        }

        $targetPage = BackendUtility::getRecordWSOL('pages', $targetUid);
        if (empty($targetPage)) {
            return;
        }

        $languageId = (int)($sourcePage['sys_language_uid'] ?? 0);
        $parentPid = (int)$targetPage['pid'];

        // Check if parent was also copied and we already processed its slug
        $parentSlug = $processedSlugs[$parentPid] ?? $this->slugGeneratorService->getParentSlug($parentPid, $languageId);

        $newSlug = $this->slugGeneratorService->combineWithParent(
            $parentSlug,
            $sourcePage['slug'] ?? '',
            $targetPage,
            $parentPid,
        );

        $newSlug = $this->slugGeneratorService->ensureUnique($newSlug, $targetPage, $parentPid, $targetUid);

        $pasteDataMap['pages'][$targetUid]['slug'] = $newSlug;
        $pasteDataMap['pages'][$targetUid]['slug_locked'] = 0;

        // Track this slug for child pages that might reference it
        $processedSlugs[$targetUid] = $newSlug;
    }
}
