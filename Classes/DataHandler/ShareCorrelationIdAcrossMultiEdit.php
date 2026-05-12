<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;

// Multi-edit issues one DataHandler run per pages record, so each slug change
// gets a fresh correlation id and core's single-revert can only undo the last
// page. When a multi-edit pages request has slug changes on more than one
// record, we share one correlation id across every outermost DataHandler whose
// own datamap actually touches a slug. Unrelated field-only DataHandlers in
// the same request keep their default correlation, so the revert button on
// the slug notification only rolls back the slug-related history.
final class ShareCorrelationIdAcrossMultiEdit
{
    /**
     * @var array<int, CorrelationId> keyed by spl_object_id of the request
     */
    private static array $sharedCorrelationIds = [];

    public function processDatamap_beforeStart(DataHandler $dataHandler): void
    {
        $request = $this->getRequest();
        if ($request === null || !$this->hasMultipleSlugChanges($request)) {
            return;
        }
        if (!$this->datamapTouchesSlug($dataHandler)) {
            return;
        }
        // Skip nested DataHandlers and any DataHandler whose caller has
        // already set an explicit correlation (cascade, move, slug rebuild) —
        // those rely on their own subject/aspects.
        if (!$dataHandler->isOuterMostInstance()) {
            return;
        }
        $currentCorrelation = $dataHandler->getCorrelationId();
        if ($currentCorrelation !== null
            && ($currentCorrelation->getSubject() !== null || $currentCorrelation->getAspects() !== [])
        ) {
            return;
        }
        $dataHandler->setCorrelationId($this->getSharedCorrelationId($request));
    }

    private function getRequest(): ?ServerRequestInterface
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        return $request instanceof ServerRequestInterface ? $request : null;
    }

    private function hasMultipleSlugChanges(ServerRequestInterface $request): bool
    {
        $parsedBody = $request->getParsedBody();
        if (!is_array($parsedBody)) {
            return false;
        }
        $pagesData = $parsedBody['data']['pages'] ?? null;
        if (!is_array($pagesData)) {
            return false;
        }
        $slugChangeCount = 0;
        foreach ($pagesData as $record) {
            if (is_array($record) && array_key_exists('slug', $record)) {
                ++$slugChangeCount;
                if ($slugChangeCount > 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function datamapTouchesSlug(DataHandler $dataHandler): bool
    {
        $pagesData = $dataHandler->datamap['pages'] ?? [];
        foreach ($pagesData as $record) {
            if (array_key_exists('slug', $record)) {
                return true;
            }
        }

        return false;
    }

    private function getSharedCorrelationId(ServerRequestInterface $request): CorrelationId
    {
        $key = spl_object_id($request);
        if (!isset(self::$sharedCorrelationIds[$key])) {
            self::$sharedCorrelationIds[$key] = CorrelationId::forScope(bin2hex(random_bytes(8)))
                ->withSubject('multiedit');
        }

        return self::$sharedCorrelationIds[$key];
    }
}
