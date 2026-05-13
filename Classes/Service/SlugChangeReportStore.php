<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use LogicException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * Request-scoped storage for the slug-change report payload.
 *
 * Two backing slots are kept in lock-step:
 *  - module-data slot 'sluggi/slugChangeReport' (works in CLI tests)
 *  - update-signal slot 'sluggi:slugChangeReport' (production dispatch trigger;
 *    no-op in CLI per BackendUtility::setUpdateSignal early-return)
 *
 * Two request-scoped maps not persisted to UC:
 *  - directlyEdited[uid] = originalSlug — pages whose slug field was in the
 *    DataHandler datamap; only these become entries[] (revert correlations).
 *  - candidates[uid] = originalSlug — every page reported via
 *    SlugRedirectChangeItemCreatedEvent (parent + cascade descendants).
 */
final class SlugChangeReportStore
{
    public const MODULE_DATA_KEY = 'sluggi/slugChangeReport';
    public const UPDATE_SIGNAL_KEY = 'sluggi:slugChangeReport';

    /**
     * @var array<int, string>
     */
    private array $directlyEdited = [];

    /**
     * @var array<int, string>
     */
    private array $candidates = [];

    /**
     * @var array<int, true>
     */
    private array $countedCandidates = [];

    public function markDirectlyEdited(int $pageId, string $originalSlug): void
    {
        $this->directlyEdited[$pageId] = $originalSlug;
    }

    public function markCandidate(int $pageId, string $originalSlug): void
    {
        if (!isset($this->candidates[$pageId])) {
            $this->candidates[$pageId] = $originalSlug;
        }
    }

    public function isDirectlyEdited(int $pageId): bool
    {
        return isset($this->directlyEdited[$pageId]);
    }

    public function markCounted(int $pageId): bool
    {
        if (isset($this->countedCandidates[$pageId])) {
            return false;
        }
        $this->countedCandidates[$pageId] = true;

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function getCandidates(): array
    {
        return $this->candidates;
    }

    /**
     * @return array{entries: array<int, array{pageId:int, correlations: array<string,string>}>, pagesUpdated: int, redirectsCreated: int}|null
     */
    public function getReport(BackendUserAuthentication $beUser): ?array
    {
        $data = $beUser->getModuleData(self::MODULE_DATA_KEY, 'ses');
        if ($data === null) {
            return null;
        }
        if (!is_array($data) || !isset($data['entries'], $data['pagesUpdated'], $data['redirectsCreated'])) {
            throw new LogicException('Corrupted slug-change report payload in module data slot.', 1747156800);
        }

        return $data;
    }

    /**
     * @param array<string, string> $correlations
     */
    public function addEntry(BackendUserAuthentication $beUser, int $pageId, array $correlations): void
    {
        $report = $this->getReport($beUser) ?? self::empty();
        $report['entries'][$pageId] = ['pageId' => $pageId, 'correlations' => $correlations];
        $this->persist($beUser, $report);
    }

    public function incrementPagesUpdated(BackendUserAuthentication $beUser, int $delta = 1): void
    {
        $report = $this->getReport($beUser) ?? self::empty();
        $report['pagesUpdated'] += $delta;
        $this->persist($beUser, $report);
    }

    public function incrementRedirectsCreated(BackendUserAuthentication $beUser, int $delta = 1): void
    {
        $report = $this->getReport($beUser) ?? self::empty();
        $report['redirectsCreated'] += $delta;
        $this->persist($beUser, $report);
    }

    /**
     * @return array{entries: array<int, array{pageId:int, correlations: array<string,string>}>, pagesUpdated: int, redirectsCreated: int}
     */
    private static function empty(): array
    {
        return ['entries' => [], 'pagesUpdated' => 0, 'redirectsCreated' => 0];
    }

    /**
     * @param array{entries: array<int, array{pageId:int, correlations: array<string,string>}>, pagesUpdated: int, redirectsCreated: int} $report
     */
    private function persist(BackendUserAuthentication $beUser, array $report): void
    {
        $beUser->pushModuleData(self::MODULE_DATA_KEY, $report);
        BackendUtility::setUpdateSignal(self::UPDATE_SIGNAL_KEY, self::flattenForDispatch($report));
    }

    /**
     * @param array{entries: array<int, array{pageId:int, correlations: array<string,string>}>, pagesUpdated: int, redirectsCreated: int} $report
     *
     * @return array{entries: list<array{pageId:int, correlations: array<string,string>}>, pagesUpdated: int, redirectsCreated: int}
     */
    private static function flattenForDispatch(array $report): array
    {
        return [
            'entries' => array_values($report['entries']),
            'pagesUpdated' => $report['pagesUpdated'],
            'redirectsCreated' => $report['redirectsCreated'],
        ];
    }
}
