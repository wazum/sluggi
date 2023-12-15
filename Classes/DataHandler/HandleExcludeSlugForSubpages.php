<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Service\SlugService;

// This class handles compatibility with "masi"'s exclude slug switch
final class HandleExcludeSlugForSubpages implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private Context $context;
    private PageRepository $pageRepository;
    private SlugService $slugService;
    /**
     * @var array<int, bool>
     */
    private static array $excludedSlugForSubpages = [];

    public function __construct(
        Context $context,
        PageRepository $pageRepository,
        SlugService $slugService
    ) {
        $this->context = $context;
        $this->pageRepository = $pageRepository;
        $this->slugService = $slugService;
    }

    /**
     * @param array<array-key, mixed> $fields
     * @param string|int              $id
     */
    public function processDatamap_preProcessFieldArray(
        array &$fields,
        string $table,
        $id,
        DataHandler $dataHandler
    ): void {
        if (!$this->shouldRun('update', $table, $fields)) {
            return;
        }

        $pageRecord = BackendUtility::getRecordWSOL($table, (int) $id);
        if (null === $pageRecord) {
            /* @psalm-suppress PossiblyNullReference */
            $this->logger->warning(sprintf('Unable to get page record with ID "%s"', $id));

            return;
        }

        if ($pageRecord['sys_language_uid'] > 0) {
            $pageRecord = BackendUtility::getRecordWSOL($table, (int) $pageRecord['l10n_parent']);
            // There's always a parent record for translated pages,
            // as the exclude field is only available in the default language
            self::$excludedSlugForSubpages[$id] = self::$excludedSlugForSubpages[(int) $pageRecord['uid']];

            return;
        }

        self::$excludedSlugForSubpages[$id] = [
            (bool) $pageRecord['exclude_slug_for_subpages'],
            (bool) $fields['exclude_slug_for_subpages']
        ];
    }

    /**
     * @param string|int $id
     *
     * @throws \Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function processDatamap_afterAllOperations(
        DataHandler $dataHandler
    ): void {
        foreach (self::$excludedSlugForSubpages as $id => $excludeSlugForSubpages) {
            [$pageExcludeSlugForSubpages, $fieldExcludeSlugForSubpages] = $excludeSlugForSubpages;
            if ($pageExcludeSlugForSubpages === $fieldExcludeSlugForSubpages) {
                continue;
            }

            $pageRecord = BackendUtility::getRecordWSOL('pages', $id);
            if (null === $pageRecord) {
                /* @psalm-suppress PossiblyNullReference */
                $this->logger->warning(sprintf('Unable to get page record with ID "%s"', $id));

                return;
            }

            // We update all child pages as the flag changed
            $fieldConfig = $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? [];
            $slugHelper = GeneralUtility::makeInstance(SlugHelper::class, 'pages', 'slug', $fieldConfig);
            $pageId = 0 === $pageRecord['sys_language_uid'] ? (int) $pageRecord['uid'] : (int) $pageRecord['l10n_parent'];
            $subPageRecords = $this->resolveSubPages($pageId, $pageRecord['sys_language_uid']);
            foreach ($subPageRecords as $subPageRecord) {
                if ($this->shouldApplySubpageUpdate($subPageRecord)) {
                    $slug = $slugHelper->generate($subPageRecord, $subPageRecord['pid']);
                    $this->persistNewSlug((int) $subPageRecord['uid'], $slug, $dataHandler->getCorrelationId());
                }
            }

            unset(self::$excludedSlugForSubpages[$id]);
        }
    }

    /**
     * Method copied from \TYPO3\CMS\Redirects\Service\SlugService.
     *
     * @throws \Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    private function resolveSubPages(int $id, int $languageUid): array
    {
        // First resolve all sub-pages in default language
        $queryBuilder = $this->getQueryBuilderForPages();
        $subPages = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->orderBy('uid', 'ASC')
            ->execute()
            ->fetchAllAssociative();

        // If the language is not the default language, resolve the language related records.
        if ($languageUid > 0) {
            $queryBuilder = $this->getQueryBuilderForPages();
            $subPages = $queryBuilder
                ->select('*')
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->in('l10n_parent', $queryBuilder->createNamedParameter(array_column($subPages, 'uid'), Connection::PARAM_INT_ARRAY)),
                    $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT))
                )
                ->orderBy('uid', 'ASC')
                ->execute()
                ->fetchAllAssociative();
        }
        $results = [];
        if (!empty($subPages)) {
            $subPages = $this->pageRepository->getPagesOverlay($subPages, $languageUid);
            foreach ($subPages as $subPage) {
                // Only one level deep, rest is done by the Core recursively
                $results[] = $subPage;
            }
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $pageRecord
     */
    private function shouldApplySubpageUpdate(array $pageRecord): bool
    {
        return !$pageRecord['slug_locked'] && $pageRecord['tx_sluggi_sync'];
    }

    private function persistNewSlug(int $uid, string $newSlug, CorrelationId $correlationId): void
    {
        $this->disableHook();
        $data = [];
        $data['pages'][$uid]['slug'] = $newSlug;
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->setCorrelationId($correlationId);
        $dataHandler->process_datamap();
        $this->enabledHook();
    }

    private function getQueryBuilderForPages(): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $this->context->getPropertyFromAspect('workspace', 'id')));

        return $queryBuilder;
    }

    private function enabledHook(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi-exclude'] = __CLASS__;
    }

    private function disableHook(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi-exclude']);
    }

    private function shouldRun(string $status, string $table, array $fields): bool
    {
        return 'update' === $status && 'pages' === $table && isset($fields['exclude_slug_for_subpages']);
    }
}
