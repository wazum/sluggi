<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Service\ReservedPathService;
use Wazum\Sluggi\Utility\DataHandlerUtility;

final readonly class ValidateReservedSlugPath
{
    public function __construct(
        private ReservedPathService $reservedPathService,
    ) {
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_postProcessFieldArray(
        string $status,
        string $table,
        string|int $id,
        array &$fieldArray,
        DataHandler $dataHandler,
    ): void {
        if ($table !== 'pages' || !array_key_exists('slug', $fieldArray)) {
            return;
        }

        if (DataHandlerUtility::isNestedSlugUpdate($dataHandler)) {
            return;
        }

        $pageId = is_int($id) ? $id : (int)($fieldArray['pid'] ?? 0);
        $site = $this->reservedPathService->findSiteForPage($pageId);
        if ($site === null) {
            return;
        }

        $patterns = $this->reservedPathService->getReservedPathsForSite($site);
        if ($patterns === []) {
            return;
        }

        $slug = (string)$fieldArray['slug'];
        if (!$this->reservedPathService->isReserved($slug, $patterns)) {
            return;
        }

        // Clear the slug field — TYPO3 core fills in a fallback from the title.
        // Update: previous slug stays in the DB. Create: the record is saved
        // with core's fallback slug and the editor sees the flash error so
        // they can fix the slug without losing the other form data.
        unset($fieldArray['slug']);
        DataHandlerUtility::logSlugValidationError(
            $dataHandler,
            is_int($id) ? $id : 0,
            'error.reservedPath',
        );
    }
}
