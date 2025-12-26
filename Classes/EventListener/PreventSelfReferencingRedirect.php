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
        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder
            ->delete('sys_redirect')
            ->where(
                $queryBuilder->expr()->eq('source_path', $queryBuilder->createNamedParameter($slug)),
                $queryBuilder->expr()->eq('source_host', $queryBuilder->createNamedParameter($sourceHost))
            )
            ->executeStatement();
    }
}
