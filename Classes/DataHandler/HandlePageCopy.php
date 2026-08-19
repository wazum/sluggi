<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Service\SlugGeneratorService;
use Wazum\Sluggi\Utility\DataHandlerUtility;

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

        $this->updateSlugsForCopiedPages($dataHandler);
    }

    private function updateSlugsForCopiedPages(DataHandler $dataHandler): void
    {
        $data = [];
        $processedSlugs = [];

        foreach ($dataHandler->copyMappingArray['pages'] ?? [] as $sourceUid => $targetUid) {
            $newSlug = $this->resolveSlugForCopiedPage((int)$sourceUid, (int)$targetUid, $processedSlugs);
            if ($newSlug === null) {
                continue;
            }

            $data['pages'][(int)$targetUid] = ['slug' => $newSlug, 'slug_locked' => 0];
        }

        if ($data === []) {
            return;
        }

        // Marked as a relocation so the validation hooks let it through.
        $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $localDataHandler->start($data, []);
        $localDataHandler->setCorrelationId(
            DataHandlerUtility::correlationIdWithAspect($dataHandler, DataHandlerUtility::COPY_CORRELATION_ASPECT)
        );
        $localDataHandler->process_datamap();
    }

    /**
     * @param array<int, array<int, string>> $processedSlugs
     */
    private function resolveSlugForCopiedPage(
        int $sourceUid,
        int $targetUid,
        array &$processedSlugs,
    ): ?string {
        $sourcePage = BackendUtility::getRecordWSOL('pages', $sourceUid);
        if (empty($sourcePage)) {
            return null;
        }

        $targetPage = BackendUtility::getRecordWSOL('pages', $targetUid);
        if (empty($targetPage)) {
            return null;
        }

        $languageId = (int)($sourcePage['sys_language_uid'] ?? 0);
        $parentPid = (int)$targetPage['pid'];

        $parentSlug = $processedSlugs[$parentPid][$languageId]
            ?? $this->slugGeneratorService->getParentSlug($parentPid, $languageId);

        $newSlug = $this->slugGeneratorService->reparentSlug(
            $parentSlug,
            $sourcePage['slug'] ?? '',
            $targetPage,
            $parentPid,
        );

        $newSlug = $this->slugGeneratorService->ensureUnique($newSlug, $targetPage, $parentPid, $targetUid);

        // Children carry the default-language uid as pid, so a translation
        // registers under its l10n_parent.
        $cacheUid = $languageId === 0 ? $targetUid : (int)($targetPage['l10n_parent'] ?? 0);
        if ($cacheUid > 0) {
            $processedSlugs[$cacheUid][$languageId] = $newSlug;
        }

        return $newSlug;
    }
}
