<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Redirects\Event\SlugRedirectChangeItemCreatedEvent;
use TYPO3\CMS\Redirects\Service\SlugService;
use Wazum\Sluggi\Service\SlugChangeReportStore;

final readonly class CollectSlugChangeReport
{
    public function __construct(
        private SlugChangeReportStore $store,
        private ConnectionPool $connectionPool,
    ) {
    }

    /**
     * Runs in post-process (not pre-process) so it sees the slug whether the
     * user submitted it directly or sluggi's HandlePageUpdate auto-regenerated
     * it from changed source fields. Our hook is registered last in
     * ext_localconf.php, so we run after every other sluggi post-process hook
     * has had a chance to reject/clear the slug.
     *
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_postProcessFieldArray(
        string $status,
        string $table,
        int|string $id,
        array &$fieldArray,
        DataHandler $dataHandler,
    ): void {
        if ($table !== 'pages' || !array_key_exists('slug', $fieldArray)) {
            return;
        }
        if (!is_numeric($id)) {
            return;
        }
        if (!$dataHandler->isOuterMostInstance() || $this->correlationHasSlugServiceAspect($dataHandler)) {
            return;
        }
        $pageId = (int)$id;
        $originalSlug = $this->fetchCurrentSlug($pageId);
        if ($originalSlug === null) {
            return;
        }
        $this->store->markDirectlyEdited($pageId, $originalSlug);
        $this->store->markCandidate($pageId, $originalSlug);
    }

    public function __invoke(SlugRedirectChangeItemCreatedEvent $event): void
    {
        $changeItem = $event->getSlugRedirectChangeItem();
        $pageId = $changeItem->getPageId();
        $originalSlug = (string)($changeItem->getOriginal()['slug'] ?? '');
        if ($originalSlug === '') {
            return;
        }
        $this->store->markCandidate($pageId, $originalSlug);
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        int|string $id,
        array $fieldArray,
        DataHandler $dataHandler,
    ): void {
        if ($table !== 'pages') {
            return;
        }

        foreach ($this->store->getCandidates() as $candidateUid => $originalSlug) {
            $row = $this->fetchCurrentRow($candidateUid);
            if ($row === null || $row['slug'] === $originalSlug) {
                continue;
            }
            if (!$this->store->markCounted($candidateUid)) {
                continue;
            }
            $this->store->incrementPagesUpdated();
            if ($this->store->isDirectlyEdited($candidateUid)) {
                $this->store->addEntry(
                    $candidateUid,
                    $row['title'],
                    $this->buildCorrelations($dataHandler, $candidateUid),
                );
            }
        }
    }

    /**
     * @return array{slug: string, title: string}|null
     */
    private function fetchCurrentRow(int $pageId): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('slug', 'title')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? ['slug' => (string)$row['slug'], 'title' => (string)$row['title']] : null;
    }

    private function fetchCurrentSlug(int $pageId): ?string
    {
        return $this->fetchCurrentRow($pageId)['slug'] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function buildCorrelations(DataHandler $dataHandler, int $pageId): array
    {
        $base = $dataHandler->getCorrelationId() ?? CorrelationId::forScope(bin2hex(random_bytes(8)));
        if ($base->getSubject() === null) {
            $base = $base->withSubject(md5('pages:' . $pageId));
        }

        return [
            'correlationIdSlugUpdate' => (string)$base->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'slug'),
            'correlationIdRedirectCreation' => (string)$base->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'redirect'),
        ];
    }

    private function correlationHasSlugServiceAspect(DataHandler $dataHandler): bool
    {
        $correlation = $dataHandler->getCorrelationId();
        if ($correlation === null) {
            return false;
        }

        return in_array(SlugService::CORRELATION_ID_IDENTIFIER, $correlation->getAspects(), true);
    }
}
