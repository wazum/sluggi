<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Upgrade;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use Wazum\Sluggi\Service\MasiCompatibilityService;

#[UpgradeWizard('sluggi_coupleMasiExclusion')]
final readonly class CoupleMasiExclusionWizard implements UpgradeWizardInterface
{
    public function __construct(
        private MasiCompatibilityService $masiCompatibilityService,
        private ConnectionPool $connectionPool,
    ) {
    }

    public function getTitle(): string
    {
        return 'Align the masi subpage exclusion with the default language';
    }

    public function getDescription(): string
    {
        return 'sluggi couples pages.' . $this->masiCompatibilityService->getExclusionFieldName()
            . ' to the default language, because a per language value diverges from the page paths '
            . 'sluggi resolves. This copies the default language value onto translations that were '
            . 'set differently before. URL paths are not regenerated — use the recursive URL path '
            . 'update on the affected pages if any of them are wrong.';
    }

    public function executeUpdate(): bool
    {
        if (!$this->masiCompatibilityService->isActive()) {
            return true;
        }

        $fieldName = $this->masiCompatibilityService->getExclusionFieldName();
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $rows = $queryBuilder
            ->select('translation.uid AS uid')
            ->addSelectLiteral($queryBuilder->quoteIdentifier('original.' . $fieldName) . ' AS ' . $queryBuilder->quoteIdentifier('target'))
            ->from('pages', 'translation')
            ->innerJoin(
                'translation',
                'pages',
                'original',
                $queryBuilder->expr()->eq('original.uid', $queryBuilder->quoteIdentifier('translation.l10n_parent'))
            )
            ->where(
                $queryBuilder->expr()->gt('translation.sys_language_uid', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                $queryBuilder->expr()->neq('translation.' . $fieldName, $queryBuilder->quoteIdentifier('original.' . $fieldName))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ([0, 1] as $value) {
            $uids = [];
            foreach ($rows as $row) {
                if ((int)$row['target'] === $value) {
                    $uids[] = (int)$row['uid'];
                }
            }
            if ($uids === []) {
                continue;
            }

            $updateQueryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
            $updateQueryBuilder
                ->update('pages')
                ->set($fieldName, $value)
                ->where($updateQueryBuilder->expr()->in('uid', $uids))
                ->executeStatement();
        }

        return true;
    }

    public function updateNecessary(): bool
    {
        if (!$this->masiCompatibilityService->isActive()) {
            return false;
        }

        $fieldName = $this->masiCompatibilityService->getExclusionFieldName();
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

        return (int)$queryBuilder
            ->count('translation.uid')
            ->from('pages', 'translation')
            ->innerJoin(
                'translation',
                'pages',
                'original',
                $queryBuilder->expr()->eq('original.uid', $queryBuilder->quoteIdentifier('translation.l10n_parent'))
            )
            ->where(
                $queryBuilder->expr()->gt('translation.sys_language_uid', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                $queryBuilder->expr()->neq('translation.' . $fieldName, $queryBuilder->quoteIdentifier('original.' . $fieldName))
            )
            ->executeQuery()
            ->fetchOne() > 0;
    }

    /**
     * @return list<class-string>
     */
    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }
}
