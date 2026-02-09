<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Service\SlugGeneratorService;

final readonly class HandlePageUndelete
{
    public function __construct(
        private SlugGeneratorService $slugGeneratorService,
    ) {
    }

    public function processCmdmap_postProcess(
        string $command,
        string $table,
        string|int $id,
        mixed $value,
        DataHandler $dataHandler,
    ): void {
        if ($command !== 'undelete' || $table !== 'pages') {
            return;
        }

        $page = BackendUtility::getRecordWSOL('pages', (int)$id);
        if (empty($page)) {
            return;
        }

        $currentSlug = $page['slug'] ?? '';
        if ($currentSlug === '') {
            return;
        }

        $uniqueSlug = $this->slugGeneratorService->ensureUnique($currentSlug, $page, (int)$page['pid'], (int)$id);

        if ($uniqueSlug !== $currentSlug) {
            $updateDataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $updateDataHandler->start(
                ['pages' => [(int)$id => ['slug' => $uniqueSlug]]],
                []
            );
            $updateDataHandler->process_datamap();
        }
    }
}
