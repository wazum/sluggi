<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandling;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\Model\RecordState;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper as CoreSlugHelper;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use Wazum\Sluggi\Service\PageTranslationResolver;
use Wazum\Sluggi\Service\SlugGenerationRecordResolver;
use Wazum\Sluggi\Slug\SlugNormalizer;

class SlugHelper extends CoreSlugHelper
{
    protected SlugNormalizer $sluggiNormalizer;

    /**
     * @var array<int, int>
     */
    private array $parentPageIds = [];

    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(string $tableName, string $fieldName, array $configuration, int $workspaceId = 0)
    {
        parent::__construct($tableName, $fieldName, self::applyDefaultReplacements($configuration), $workspaceId);
        $this->sluggiNormalizer = GeneralUtility::makeInstance(SlugNormalizer::class);
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    public static function applyDefaultReplacements(array $configuration): array
    {
        // Core treats "/" in source-field values as a path separator during
        // generation; a TCA replacements entry for "/" wins over this default.
        // Callers that pass the configuration to postModifiers themselves must
        // route it through here, or a postModifier sees a different
        // configuration depending on whether core or sluggi invoked it.
        $configuration['generatorOptions']['replacements']['/'] ??= (string)($configuration['fallbackCharacter'] ?? '-');

        return $configuration;
    }

    /**
     * @param array<string, mixed> $recordData
     */
    public function generate(array $recordData, int $pid): string
    {
        return parent::generate(
            GeneralUtility::makeInstance(SlugGenerationRecordResolver::class)->resolve(
                $recordData,
                $this->tableName,
                $this->fieldName,
                $pid,
                $this->workspaceId,
                $this->configuration,
            ),
            $pid
        );
    }

    public function sanitize(string $slug): string
    {
        $value = $this->sluggiNormalizer->normalize($slug, $this->configuration['fallbackCharacter'] ?? '-');
        if (($value[0] ?? '') !== '/' && $this->prependSlashInSlug) {
            $value = '/' . $value;
        }

        return $value;
    }

    public function isUniqueInSite(string $slug, RecordState $state): bool
    {
        $queryBuilder = $this->createPreparedQueryBuilder();
        $this->applySlugConstraint($queryBuilder, $slug);
        $this->applyRecordConstraint($queryBuilder, $state->getSubject()->getIdentifier());
        $this->applyLanguageConstraint($queryBuilder, $state->getContext()->getLanguageId());
        $this->applyWorkspaceConstraint($queryBuilder, $state);
        $records = $this->resolveVersionOverlays($queryBuilder->executeQuery()->fetchAllAssociative());
        if ($records === []) {
            return true;
        }

        $pageId = $state->resolveNodeAggregateIdentifier();
        if (!MathUtility::canBeInterpretedAsInteger($pageId)) {
            $pageId = $state->getNode()->getIdentifier();
        }
        $pageId = (int)$pageId;
        $recordPageIds = array_values(array_map(
            fn (array $record): int => (int)RecordStateFactory::forName($this->tableName)->fromArray($record)->resolveNodeAggregateIdentifier(),
            $records
        ));

        $rootPageIds = $this->resolveRootPageIds([$pageId, ...$recordPageIds]);
        $rootPageId = $rootPageIds[$pageId];
        if ($rootPageId === null) {
            return true;
        }
        foreach ($recordPageIds as $recordPageId) {
            if ($rootPageIds[$recordPageId] === $rootPageId) {
                return false;
            }
        }

        return true;
    }

    /**
     * Override core's hardcoded exclusion of Spacer (199) and Sysfolder (254)
     * with the configurable exclude_doktypes setting.
     *
     * @return array<string, mixed>|null
     */
    protected function resolveParentPageRecord(int $pid, int $languageId): ?array
    {
        $excludeConfig = (string)($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] ?? '');
        $excludeDokTypes = $excludeConfig !== ''
            ? array_map(intval(...), array_filter(explode(',', $excludeConfig)))
            : [];

        $rootLine = BackendUtility::BEgetRootLine($pid, '', true, ['nav_title']);
        do {
            $parentPageRecord = array_shift($rootLine);
        } while (!empty($rootLine) && in_array((int)$parentPageRecord['doktype'], $excludeDokTypes, true));

        $translation = GeneralUtility::makeInstance(PageTranslationResolver::class)
            ->resolve((int)$parentPageRecord['uid'], $languageId, $this->workspaceId);

        return $translation ?? $parentPageRecord;
    }

    /**
     * @param list<int> $pageIds
     *
     * @return array<int, int|null>
     */
    private function resolveRootPageIds(array $pageIds): array
    {
        $siteRootPageIds = [];
        foreach (GeneralUtility::makeInstance(SiteFinder::class)->getAllSites() as $site) {
            $siteRootPageIds[$site->getRootPageId()] = true;
        }

        $rootPageIds = [];
        $ancestorIds = array_combine($pageIds, $pageIds);
        for ($depth = 0; $ancestorIds !== [] && $depth < 1000; ++$depth) {
            foreach ($ancestorIds as $pageId => $ancestorId) {
                if (isset($siteRootPageIds[$ancestorId])) {
                    $rootPageIds[$pageId] = $ancestorId;
                    unset($ancestorIds[$pageId]);
                } elseif ($ancestorId <= 0) {
                    $rootPageIds[$pageId] = null;
                    unset($ancestorIds[$pageId]);
                }
            }
            if ($ancestorIds === []) {
                break;
            }
            $parentPageIds = $this->fetchParentPageIds(array_values(array_unique($ancestorIds)));
            foreach ($ancestorIds as $pageId => $ancestorId) {
                $ancestorIds[$pageId] = $parentPageIds[$ancestorId] ?? 0;
            }
        }

        return $rootPageIds + array_fill_keys(array_keys($ancestorIds), null);
    }

    /**
     * @param list<int> $pageIds
     *
     * @return array<int, int>
     */
    private function fetchParentPageIds(array $pageIds): array
    {
        $unknownPageIds = array_diff($pageIds, array_keys($this->parentPageIds));
        if ($unknownPageIds === []) {
            return $this->parentPageIds;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $rows = $queryBuilder
            ->select('uid', 'pid', 't3ver_oid', 't3ver_state', 't3ver_wsid')
            ->from('pages')
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter(array_values($unknownPageIds), Connection::PARAM_INT_ARRAY)))
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            BackendUtility::workspaceOL('pages', $row, $this->workspaceId, false);
            if (is_array($row)) {
                $this->parentPageIds[(int)$row['uid']] = (int)$row['pid'];
            }
        }

        return $this->parentPageIds;
    }
}
