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

        if (DataHandlerUtility::isNewRecord($id)) {
            // On create we can't just clear the slug — TYPO3 then falls back to
            // the empty default which ends up as '/' and collides with the site
            // root. Rewrite the first path segment with a random suffix so the
            // record gets a valid, non-reserved, obviously temporary slug that
            // the editor has to fix. Client-side submit blocking prevents this
            // path under normal editor workflows; this runs as a safety net.
            $fieldArray['slug'] = $this->placeholderSlugFor($slug);
        } else {
            unset($fieldArray['slug']);
        }
        DataHandlerUtility::logSlugValidationError(
            $dataHandler,
            is_int($id) ? $id : 0,
            'error.reservedPath',
        );
    }

    private function placeholderSlugFor(string $reservedSlug): string
    {
        $suffix = bin2hex(random_bytes(5));
        $segments = explode('/', ltrim($reservedSlug, '/'));
        $segments[0] = ($segments[0] === '' ? 'page' : $segments[0]) . '-' . $suffix;

        return '/' . implode('/', $segments);
    }
}
