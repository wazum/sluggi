<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Service\SlugGeneratorService;
use Wazum\Sluggi\Service\SlugLockService;

final readonly class HandlePageMove
{
    public function __construct(
        private SlugGeneratorService $slugGeneratorService,
        private SlugLockService $lockService,
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

        $this->updateSlugForMovedPage($id, $targetId, $dataHandler);
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

        $this->updateSlugForMovedPage($id, $targetId, $dataHandler);
    }

    private function updateSlugForMovedPage(int $id, int $targetId, DataHandler $dataHandler): void
    {
        $currentPage = BackendUtility::getRecordWSOL('pages', $id);
        if (empty($currentPage)) {
            return;
        }

        if ($this->lockService->isLocked($currentPage)) {
            return;
        }

        $languageId = (int)($currentPage['sys_language_uid'] ?? 0);
        $parentSlug = $this->slugGeneratorService->getParentSlug($targetId, $languageId);
        $newSlug = $this->slugGeneratorService->combineWithParent(
            $parentSlug,
            $currentPage['slug'] ?? '',
            $currentPage,
            $targetId,
        );

        $data = ['pages' => [$id => ['slug' => $newSlug]]];
        $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $localDataHandler->start($data, []);
        $correlationId = $dataHandler->getCorrelationId();
        if ($correlationId !== null) {
            $localDataHandler->setCorrelationId($correlationId);
        }
        $localDataHandler->process_datamap();
    }
}
