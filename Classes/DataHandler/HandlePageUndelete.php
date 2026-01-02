<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class HandlePageUndelete
{
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

        $page = BackendUtility::getRecord('pages', (int)$id);
        if (empty($page)) {
            return;
        }

        $currentSlug = $page['slug'] ?? '';
        if ($currentSlug === '') {
            return;
        }

        $state = RecordStateFactory::forName('pages')->fromArray($page, (int)$page['pid'], (int)$id);
        $uniqueSlug = $this->getSlugHelper()->buildSlugForUniqueInSite($currentSlug, $state);

        if ($uniqueSlug !== $currentSlug) {
            $updateDataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $updateDataHandler->start(
                ['pages' => [(int)$id => ['slug' => $uniqueSlug]]],
                []
            );
            $updateDataHandler->process_datamap();
        }
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
