<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Helper\SlugHelper as SluggiSlugHelper;

final class HandlePageCopy
{
    /**
     * @readonly
     */
    private \TYPO3\CMS\Core\DataHandling\SlugHelper $slugHelper;

    public function __construct()
    {
        $this->slugHelper = GeneralUtility::makeInstance(
            SlugHelper::class,
            'pages',
            'slug',
            $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? []
        );
    }

    /**
     * @param string|int $id
     */
    public function processCmdmap_postProcess(
        string $command,
        string $table,
        $id,
        $value,
        DataHandler $dataHandler,
        $pasteUpdate,
        array &$pasteDataMap
    ): void {
        if ('copy' !== $command || 'pages' !== $table) {
            return;
        }

        // Important: No spaces in the fields list!!
        $currentPage = BackendUtility::getRecordWSOL('pages', $id, 'uid,slug,sys_language_uid,tx_sluggi_sync');
        if (empty($currentPage)) {
            return;
        }

        $value = (int) $value;
        $languageId = $currentPage['sys_language_uid'];
        $currentSlugSegment = SluggiSlugHelper::getLastSlugSegment($currentPage['slug']);
        // Positive value = paste into
        // Negative value = paste after
        if ($value > 0) {
            $parentPageId = $value;
        } else {
            $parentPageId = BackendUtility::getRecordWSOL('pages', \abs($value), 'pid')['pid'] ?? 0;
        }
        $parentSlug = SluggiSlugHelper::getSlug($parentPageId, $languageId);
        $newSlug = \rtrim($parentSlug, '/') . $currentSlugSegment;

        $targetPageId = $dataHandler->copyMappingArray['pages'][$id] ?? 0;
        if ($targetPageId) {
            $targetPage = BackendUtility::getRecordWSOL('pages', $targetPageId);
            $state = RecordStateFactory::forName('pages')->fromArray($targetPage, $targetPage['pid'], $targetPageId);
            $newSlug = $this->slugHelper->buildSlugForUniqueInPid($newSlug, $state);
            $pasteDataMap['pages'][$targetPageId]['slug'] = $newSlug;

            $this->handleAllChildPages($id, $newSlug, $languageId, $dataHandler, $pasteDataMap);
        }
    }

    private function handleAllChildPages(int $id, string $parentSlug, int $languageId, DataHandler $dataHandler, array &$pasteDataMap): void
    {
        foreach ($this->getChildPagesFor($id, $languageId) as $childPage) {
            $targetPageId = $dataHandler->copyMappingArray['pages'][$childPage['uid']] ?? 0;
            if (empty($targetPageId)) {
                continue;
            }

            $currentSlugSegment = SluggiSlugHelper::getLastSlugSegment($childPage['slug']);
            $newSlug = \rtrim($parentSlug, '/') . $currentSlugSegment;

            $targetPage = BackendUtility::getRecordWSOL('pages', $targetPageId);
            $state = RecordStateFactory::forName('pages')->fromArray($targetPage, $targetPage['pid'], $targetPageId);
            $newSlug = $this->slugHelper->buildSlugForUniqueInPid($newSlug, $state);
            $pasteDataMap['pages'][$targetPageId]['slug'] = $newSlug;

            $this->handleAllChildPages($childPage['uid'], $newSlug, $languageId, $dataHandler, $pasteDataMap);
        }
    }

    private function getChildPagesFor(int $parentPageId, int $languageId): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('pages')->createQueryBuilder();

        return $queryBuilder
            ->select('uid', 'slug', 'tx_sluggi_sync')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($parentPageId, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageId, \PDO::PARAM_INT))
            )
            ->execute()->fetchAllAssociative();
    }
}
