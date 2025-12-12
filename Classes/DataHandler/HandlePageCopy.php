<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
        foreach ($copiedPages as $sourceUid => $targetUid) {
            $this->updateSlugForCopiedPage($sourceUid, $targetUid, $pasteDataMap);
        }
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $pasteDataMap
     */
    private function updateSlugForCopiedPage(int $sourceUid, int $targetUid, array &$pasteDataMap): void
    {
        $sourcePage = BackendUtility::getRecordWSOL('pages', $sourceUid, 'slug,sys_language_uid');
        if (empty($sourcePage)) {
            return;
        }

        $targetPage = BackendUtility::getRecordWSOL('pages', $targetUid);
        if (empty($targetPage)) {
            return;
        }

        $languageId = (int)($sourcePage['sys_language_uid'] ?? 0);
        $parentSlug = $this->slugGeneratorService->getParentSlug((int)$targetPage['pid'], $languageId);
        $newSlug = $this->slugGeneratorService->combineWithParent($parentSlug, $sourcePage['slug'] ?? '');

        $state = RecordStateFactory::forName('pages')->fromArray($targetPage, (int)$targetPage['pid'], $targetUid);
        $newSlug = $this->getSlugHelper()->buildSlugForUniqueInSite($newSlug, $state);

        $pasteDataMap['pages'][$targetUid]['slug'] = $newSlug;
    }

    private function getSlugHelper(): SlugHelper
    {
        return GeneralUtility::makeInstance(
            SlugHelper::class,
            'pages',
            'slug',
            $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? []
        );
    }
}
