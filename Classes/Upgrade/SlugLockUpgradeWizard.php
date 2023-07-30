<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Upgrade;

use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

final class SlugLockUpgradeWizard implements UpgradeWizardInterface
{
    public function getTitle(): string
    {
        return 'Migrate tx_sluggi_lock field';
    }

    public function getDescription(): string
    {
        return 'Migrates the tx_sluggi_lock field to slug_locked field in the pages table.';
    }

    /**
     * @throws DBALException
     */
    public function executeUpdate(): bool
    {
        $queryBuilder = $this->getPagesQueryBuilder();
        $queryBuilder->update('pages')
            ->set('slug_locked', $queryBuilder->quoteIdentifier('tx_sluggi_lock'), false)
            ->executeStatement();

        return true;
    }

    public function updateNecessary(): bool
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('pages');
        $tableInformation = $connection->getSchemaManager()->listTableDetails('pages');
        if ($tableInformation->hasColumn('tx_sluggi_lock')) {
            $queryBuilder = $this->getPagesQueryBuilder();

            return (bool) $queryBuilder->count('uid')
                ->from('pages')
                ->where('tx_sluggi_lock != slug_locked')
                ->executeQuery()->fetchOne();
        }

        return false;
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    private function getPagesQueryBuilder(): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    public function getIdentifier(): string
    {
        return 'slug_lock_upgrade_wizard';
    }
}
