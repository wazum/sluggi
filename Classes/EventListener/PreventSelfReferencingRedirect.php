<?php

declare(strict_types=1);

namespace Wazum\Sluggi\EventListener;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Redirects\Event\ModifyAutoCreateRedirectRecordBeforePersistingEvent;

final readonly class PreventSelfReferencingRedirect
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {
    }

    public function __invoke(ModifyAutoCreateRedirectRecordBeforePersistingEvent $event): void
    {
        $redirectRecord = $event->getRedirectRecord();
        $changeItem = $event->getSlugRedirectChangeItem();
        $newSlug = $changeItem->getChanged()['slug'] ?? '';
        $sourcePath = $redirectRecord['source_path'] ?? '';
        $sourceHost = $redirectRecord['source_host'] ?? '*';

        if ($newSlug === '') {
            return;
        }

        if ($this->isSelfReferencing($sourcePath, $newSlug)) {
            $redirectRecord['deleted'] = 1;
            $event->setRedirectRecord($redirectRecord);
        }

        $this->deleteExistingRedirectsForSlug($newSlug, $sourceHost);
    }

    private function isSelfReferencing(string $sourcePath, string $newSlug): bool
    {
        if ($sourcePath === '' || $newSlug === '') {
            return false;
        }

        $normalizedSource = rtrim($sourcePath, '/');
        $normalizedSlug = rtrim($newSlug, '/');

        return $normalizedSource === $normalizedSlug;
    }

    private function deleteExistingRedirectsForSlug(string $slug, string $sourceHost): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_redirect');

        // QueryBuilder restrictions only apply to SELECT, so we must scope DELETE explicitly:
        // - deleted = 0: preserve soft-deleted records
        // - creation_type = 0 (automatically created): preserve manually created redirects (1)
        $queryBuilder
            ->delete('sys_redirect')
            ->where(
                $queryBuilder->expr()->eq('source_path', $queryBuilder->createNamedParameter($slug)),
                $queryBuilder->expr()->eq('source_host', $queryBuilder->createNamedParameter($sourceHost)),
                $queryBuilder->expr()->eq('creation_type', 0),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->executeStatement();
    }
}
