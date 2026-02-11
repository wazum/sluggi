<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Upgrade;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;

#[UpgradeWizard('sluggi_clearExcludedDoktypeSlugs')]
final readonly class ClearExcludedDoktypeSlugsWizard implements UpgradeWizardInterface
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        private ConnectionPool $connectionPool,
    ) {
    }

    public function getTitle(): string
    {
        return 'Clear slugs for excluded page types';
    }

    public function getDescription(): string
    {
        $pageTypes = implode(', ', $this->extensionConfiguration->getExcludedPageTypes());

        return sprintf(
            'Removes URL slugs from pages with excluded page types (%s) to free up the slug namespace.',
            $pageTypes ?: 'none configured'
        );
    }

    public function executeUpdate(): bool
    {
        $excludedPageTypes = $this->extensionConfiguration->getExcludedPageTypes();
        if ($excludedPageTypes === []) {
            return true;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder
            ->update('pages')
            ->set('slug', '')
            ->set('tx_sluggi_sync', 0)
            ->where(
                $queryBuilder->expr()->in('doktype', $excludedPageTypes)
            )
            ->executeStatement();

        return true;
    }

    public function updateNecessary(): bool
    {
        $excludedPageTypes = $this->extensionConfiguration->getExcludedPageTypes();
        if ($excludedPageTypes === []) {
            return false;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

        $count = $queryBuilder
            ->count('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->in('doktype', $excludedPageTypes),
                $queryBuilder->expr()->neq('slug', $queryBuilder->createNamedParameter(''))
            )
            ->executeQuery()
            ->fetchOne();

        return $count > 0;
    }

    /**
     * @return list<class-string>
     */
    public function getPrerequisites(): array
    {
        return [];
    }
}
