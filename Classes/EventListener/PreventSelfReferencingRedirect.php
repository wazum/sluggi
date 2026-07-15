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

        $sourceHost = (string)($event->getRedirectRecord()['source_host'] ?? '*');
        $this->softDeleteExistingRedirectsForSlug($newSlug, $changeItem->getPageId(), $sourceHost);
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

    private function softDeleteExistingRedirectsForSlug(string $slug, int $pageId, string $sourceHost): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_redirect');

        // Auto-created redirects always carry URL parameters in their target
        // (at least &_language=), so requiring the ampersand both pins the
        // page uid exactly (uid=1 must not match uid=10) and spares manually
        // created link-wizard targets like t3://page?uid=1 without parameters.
        $queryBuilder
            ->update('sys_redirect')
            ->set('deleted', 1)
            ->where(
                $queryBuilder->expr()->eq('source_path', $queryBuilder->createNamedParameter($slug)),
                $queryBuilder->expr()->like(
                    'target',
                    $queryBuilder->createNamedParameter('t3://page?uid=' . $pageId . '&%')
                ),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('source_host', $queryBuilder->createNamedParameter($sourceHost)),
                    $queryBuilder->expr()->eq('source_host', $queryBuilder->createNamedParameter('*'))
                ),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->executeStatement();
    }
}
