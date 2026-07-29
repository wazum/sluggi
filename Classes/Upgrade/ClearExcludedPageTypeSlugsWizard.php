<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Upgrade;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\MasiCompatibilityService;

#[UpgradeWizard('sluggi_clearExcludedPageTypeSlugs')]
final readonly class ClearExcludedPageTypeSlugsWizard implements UpgradeWizardInterface
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        private MasiCompatibilityService $masiCompatibilityService,
        private ConnectionPool $connectionPool,
    ) {
    }

    public function getTitle(): string
    {
        return 'Clear slugs for excluded page types';
    }

    public function getDescription(): string
    {
        $pageTypes = implode(', ', $this->clearablePageTypes());
        $description = sprintf(
            'Removes URL slugs from pages with excluded page types (%s) to free up the slug namespace. '
            . 'The slugs are emptied with a direct database update: no record history is written and the '
            . 'change cannot be reverted. Deleted pages are left untouched.',
            $pageTypes ?: 'none configured'
        );

        if ($this->masiCompatibilityService->isActive()) {
            $description .= ' Folders (254) are left out while "masi" is installed, because each folder '
                . 'decides for itself whether it appears in the paths of its subpages.';
        }

        return $description;
    }

    public function executeUpdate(): bool
    {
        $excludedPageTypes = $this->clearablePageTypes();
        if ($excludedPageTypes === []) {
            return true;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder
            ->update('pages')
            ->set('slug', '')
            ->set('tx_sluggi_sync', 0)
            ->where(
                $queryBuilder->expr()->in('doktype', $excludedPageTypes),
                // An UPDATE carries no restrictions, so deleted pages have to be
                // left out by hand — they are not counted either, and restoring
                // one later would bring back an emptied slug.
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0)),
            )
            ->executeStatement();

        return true;
    }

    /**
     * @return list<int>
     */
    private function clearablePageTypes(): array
    {
        $pageTypes = $this->extensionConfiguration->getExcludedPageTypes();
        if (!$this->masiCompatibilityService->isActive()) {
            return $pageTypes;
        }

        // With masi a folder may well contribute its segment to the paths of its
        // subpages, so its slug is live data and must not be emptied.
        return array_values(
            array_filter($pageTypes, static fn (int $pageType): bool => $pageType !== PageRepository::DOKTYPE_SYSFOLDER)
        );
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
