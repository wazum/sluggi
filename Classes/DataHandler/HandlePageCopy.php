<?php

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Helper\SlugHelper as SluggiSlugHelper;

final class HandlePageCopy
{
    private readonly SlugHelper $slugHelper;

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
     * @param array<array-key, mixed> $pasteDataMap
     */
    public function processCmdmap_postProcess(
        string $command,
        string $table,
        string|int $id,
        string|int $value,
        DataHandler $dataHandler,
        bool $pasteUpdate,
        array &$pasteDataMap,
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

    /**
     * @param array<array-key, mixed> $pasteDataMap
     */
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

    /**
     * @return array<array-key, array<string, mixed>>
     *
     * @throws \Doctrine\DBAL\Exception
     */
    private function getChildPagesFor(int $parentPageId, int $languageId): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('pages')->createQueryBuilder();

        return $queryBuilder
            ->select('uid', 'slug', 'tx_sluggi_sync')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($parentPageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageId, Connection::PARAM_INT))
            )
            ->executeQuery()->fetchAllAssociative();
    }
}
