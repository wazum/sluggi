<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Service\SlugGeneratorService;
use Wazum\Sluggi\Utility\DataHandlerUtility;

final readonly class HandlePageMove
{
    public function __construct(
        private SlugGeneratorService $slugGeneratorService,
        private SiteFinder $siteFinder,
    ) {
    }

    /**
     * @param array<string, mixed> $moveRecord
     * @param array<string, mixed> $updateFields
     */
    public function moveRecord_afterAnotherElementPostProcess(
        string $table,
        int $id,
        int $targetId,
        int $siblingTargetId,
        array $moveRecord,
        array $updateFields,
        DataHandler $dataHandler,
    ): void {
        if ($table !== 'pages') {
            return;
        }

        $this->updateSlugForMovedPage($id, (int)($moveRecord['pid'] ?? 0), $targetId, $dataHandler);
    }

    /**
     * @param array<string, mixed> $moveRecord
     * @param array<string, mixed> $updateFields
     */
    public function moveRecord_firstElementPostProcess(
        string $table,
        int $id,
        int $targetId,
        array $moveRecord,
        array $updateFields,
        DataHandler $dataHandler,
    ): void {
        if ($table !== 'pages') {
            return;
        }

        $this->updateSlugForMovedPage($id, (int)($moveRecord['pid'] ?? 0), $targetId, $dataHandler);
    }

    private function updateSlugForMovedPage(int $id, int $previousPid, int $targetId, DataHandler $dataHandler): void
    {
        $currentPage = BackendUtility::getRecordWSOL('pages', $id);
        if (empty($currentPage)) {
            return;
        }

        $languageId = (int)($currentPage['sys_language_uid'] ?? 0);
        $parentSlug = $this->slugGeneratorService->getParentSlug($targetId, $languageId);
        $newSlug = $this->slugGeneratorService->reparentSlug(
            $parentSlug,
            $currentPage['slug'] ?? '',
            $currentPage,
            $targetId,
        );

        $this->writeSlugs([$id => ['slug' => $newSlug]], $dataHandler);
        if ($this->siteRootPageId($previousPid) !== $this->siteRootPageId($targetId)) {
            $this->makeSubpageSlugsUnique($id, $dataHandler);
        }
    }

    private function siteRootPageId(int $pageId): ?int
    {
        try {
            return $this->siteFinder->getSiteByPageId($pageId)->getRootPageId();
        } catch (SiteNotFoundException) {
            return null;
        }
    }

    private function makeSubpageSlugsUnique(int $id, DataHandler $dataHandler): void
    {
        $subpageData = [];
        foreach (array_keys($dataHandler->int_pageTreeInfo([], $id, 99, $id)) as $subpageId) {
            $subpage = BackendUtility::getRecordWSOL('pages', (int)$subpageId, 'slug');
            if (!empty($subpage['slug'])) {
                $subpageData[$subpageId] = ['slug' => $subpage['slug']];
            }
        }
        if ($subpageData !== []) {
            $this->writeSlugs($subpageData, $dataHandler);
        }
    }

    /**
     * @param array<int|string, array{slug: string}> $pageData
     */
    private function writeSlugs(array $pageData, DataHandler $dataHandler): void
    {
        $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $localDataHandler->start(['pages' => $pageData], []);
        $localDataHandler->setCorrelationId(
            DataHandlerUtility::correlationIdWithAspect($dataHandler, DataHandlerUtility::MOVE_CORRELATION_ASPECT)
        );
        $localDataHandler->process_datamap();
    }
}
