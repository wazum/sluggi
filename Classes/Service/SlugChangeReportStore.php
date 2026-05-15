<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * Request-scoped storage for the slug-change report payload.
 *
 * The report (entries, pagesUpdated, redirectsCreated) lives in instance state
 * and is mirrored to BackendUtility::setUpdateSignal on every mutation so the
 * dispatch hook receives the latest payload on the next backend page render.
 * Core clears the signal aggregator after dispatch (BackendUtility::
 * getUpdateSignalDetails calls setUpdateSignal() with no args at the end), so
 * the slot does not leak across renders. The in-memory state dies with the
 * request, so unrelated subsequent saves start fresh.
 */
final class SlugChangeReportStore
{
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

    /**
     * @var array<int, array{pageId:int, title:string, correlations: array<string,string>}>
     */
    private array $entries = [];

    private int $pagesUpdated = 0;

    private int $redirectsCreated = 0;

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
     * @return array{entries: array<int, array{pageId:int, title:string, correlations: array<string,string>}>, pagesUpdated: int, redirectsCreated: int}|null
     */
    public function getReport(): ?array
    {
        if ($this->pagesUpdated === 0 && $this->redirectsCreated === 0 && $this->entries === []) {
            return null;
        }

        return [
            'entries' => $this->entries,
            'pagesUpdated' => $this->pagesUpdated,
            'redirectsCreated' => $this->redirectsCreated,
        ];
    }

    /**
     * @param array<string, string> $correlations
     */
    public function addEntry(int $pageId, string $title, array $correlations): void
    {
        $this->entries[$pageId] = ['pageId' => $pageId, 'title' => $title, 'correlations' => $correlations];
        $this->emit();
    }

    public function incrementPagesUpdated(int $delta = 1): void
    {
        $this->pagesUpdated += $delta;
        $this->emit();
    }

    public function incrementRedirectsCreated(int $delta = 1): void
    {
        $this->redirectsCreated += $delta;
        $this->emit();
    }

    /**
     * Reset in-memory state and surgically clear our entry from the
     * setUpdateSignal aggregator. Use this when the caller has already
     * delivered the report to the client by other means (e.g. the recursive
     * context-menu controller dispatches a synthetic event after its AJAX
     * response) and the server-side render dispatch would be a duplicate.
     */
    public function discard(): void
    {
        $this->entries = [];
        $this->pagesUpdated = 0;
        $this->redirectsCreated = 0;

        $beUser = $GLOBALS['BE_USER'] ?? null;
        if (!$beUser instanceof BackendUserAuthentication) {
            return;
        }
        $aggregatorKey = BackendUtility::class . '::getUpdateSignal';
        $modData = $beUser->getModuleData($aggregatorKey, 'ses');
        if (!is_array($modData) || !isset($modData[self::UPDATE_SIGNAL_KEY])) {
            return;
        }
        unset($modData[self::UPDATE_SIGNAL_KEY]);
        $beUser->pushModuleData($aggregatorKey, $modData);
    }

    private function emit(): void
    {
        BackendUtility::setUpdateSignal(self::UPDATE_SIGNAL_KEY, [
            'entries' => array_values($this->entries),
            'pagesUpdated' => $this->pagesUpdated,
            'redirectsCreated' => $this->redirectsCreated,
        ]);
    }
}
