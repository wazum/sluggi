<?php

declare(strict_types=1);

namespace Wazum\Sluggi\EventListener;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Redirects\Event\AfterAutoCreateRedirectHasBeenPersistedEvent;
use TYPO3\CMS\Redirects\Event\ModifyAutoCreateRedirectRecordBeforePersistingEvent;

final readonly class PreventSelfReferencingRedirect
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {
    }

    public function beforePersist(ModifyAutoCreateRedirectRecordBeforePersistingEvent $event): void
    {
        $changeItem = $event->getSlugRedirectChangeItem();
        $newSlug = $changeItem->getChanged()['slug'] ?? '';

        if ($newSlug === '') {
            return;
        }

        $this->deleteExistingRedirectsForSlug($newSlug, $changeItem->getPageId());
    }

    public function afterPersist(AfterAutoCreateRedirectHasBeenPersistedEvent $event): void
    {
        $redirectRecord = $event->getRedirectRecord();
        $changeItem = $event->getSlugRedirectChangeItem();
        $newSlug = $changeItem->getChanged()['slug'] ?? '';
        $sourcePath = $redirectRecord['source_path'] ?? '';
        $uid = $redirectRecord['uid'] ?? null;

        if ($uid === null || !$this->isSelfReferencing($sourcePath, $newSlug)) {
            return;
        }

        $this->connectionPool
            ->getConnectionForTable('sys_redirect')
            ->update('sys_redirect', ['deleted' => 1], ['uid' => (int)$uid]);
    }

    private function isSelfReferencing(string $sourcePath, string $newSlug): bool
    {
        if ($sourcePath === '' || $newSlug === '') {
            return false;
        }

        return rtrim($sourcePath, '/') === rtrim($newSlug, '/');
    }

    private function deleteExistingRedirectsForSlug(string $slug, int $pageId): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_redirect');

        $queryBuilder
            ->delete('sys_redirect')
            ->where(
                $queryBuilder->expr()->eq('source_path', $queryBuilder->createNamedParameter($slug)),
                $queryBuilder->expr()->like(
                    'target',
                    $queryBuilder->createNamedParameter('t3://page?uid=' . $pageId . '%')
                ),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->executeStatement();
    }
}
