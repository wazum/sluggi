<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Hook;

use Doctrine\DBAL\Connection;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;;
use TYPO3\CMS\Redirects\Service\SlugService;
use Wazum\Sluggi\Helper\SlugHelper;

/**
 * Class DatamapHook
 *
 * @package Wazum\Sluggi\Backend\Hook
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class DatamapHook
{

    /**
     * @var PageRepository
     */
    protected $pageRepository;

    /**
     * @param SlugService $slugService
     */
    public function __construct(PageRepository $pageRepository)
    {
        $this->pageRepository = $pageRepository;
    }


    /**
     * @param string $table
     * @param int $id
     * @param int $targetId
     * @param int $siblingTargetId
     * @param array $moveRecord
     * @param array $updateFields
     * @param DataHandler $dataHandler
     */
    public function moveRecord_afterAnotherElementPostProcess(
        /** @noinspection PhpUnusedParameterInspection */
        string $table,
        int $id,
        int $targetId,
        int $siblingTargetId,
        array $moveRecord,
        array $updateFields,
        DataHandler $dataHandler
    ): void {
        if ($table !== 'pages') {
            return;
        }

        $this->updateSlugForMovedPage($id, $targetId, $dataHandler);
    }

    /**
     * @param string $table
     * @param int $id
     * @param int $targetId
     * @param array $moveRecord
     * @param array $updateFields
     * @param DataHandler $dataHandler
     */
    public function moveRecord_firstElementPostProcess(
        /** @noinspection PhpUnusedParameterInspection */
        string $table,
        int $id,
        int $targetId,
        array $moveRecord,
        array $updateFields,
        DataHandler $dataHandler
    ): void {
        if ($table !== 'pages') {
            return;
        }

        $this->updateSlugForMovedPage($id, $targetId, $dataHandler);
    }

    protected function updateSlugForMovedPage(int $id, int $targetId, DataHandler $dataHandler): void
    {
        $currentPage = BackendUtility::getRecord('pages', $id, 'uid, slug, sys_language_uid, pid, t3ver_wsid, t3ver_state, t3ver_move_id');

        $movedInWs =  ($currentPage['t3ver_move_id']>0 and $currentPage['t3ver_state']>0);

        if (!empty($currentPage)) {
            $languageId = $currentPage['sys_language_uid'];

            if($movedInWs){
                $movedRecord = BackendUtility::getRecord('pages', $currentPage['t3ver_move_id'], 'uid, slug, sys_language_uid');
                $currentSlugSegment = SlugHelper::getLastSlugSegment($movedRecord['slug']);
            }else{
                $currentSlugSegment = SlugHelper::getLastSlugSegment($currentPage['slug']);
            }

            $parentSlug = SlugHelper::getSlug($targetId, $languageId);
            $newSlug = rtrim($parentSlug, '/') . $currentSlugSegment;

            if($movedInWs) {
                $workspaceVersion = BackendUtility::getWorkspaceVersionOfRecord($dataHandler->BE_USER->workspace, 'pages', $currentPage['t3ver_move_id'], 'uid,t3ver_oid');
                if($workspaceVersion && $workspaceVersion['uid']>0){
                    GeneralUtility::makeInstance(ConnectionPool::class)
                        ->getConnectionForTable('pages')
                        ->update('pages', ['slug' => $newSlug], ['uid' => (int)$workspaceVersion['uid']]);

                    $subpages = $this->resolveSubPages($currentPage['t3ver_move_id'], $languageId);
                    foreach ($subpages as $subPage) {
                        // resolveSubPages needs the page id of the default language
                        $pageId = $languageId === 0 ? (int)$subPage['uid'] : (int)$subPage['l10n_parent'];
                        $data = [];
                        $currentSlugSegment = SlugHelper::getLastSlugSegment($subPage['slug']);
                        $newSlug = rtrim($newSlug, '/') . $currentSlugSegment;
                        $data['pages'][$pageId]['slug'] = $newSlug;
                        /** @var DataHandler $localDataHandler */
                        $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
                        $localDataHandler->start($data, []);
                        $localDataHandler->setCorrelationId($dataHandler->getCorrelationId());
                        $localDataHandler->process_datamap();
                    }
                }
            }else {

                $data = [];
                $data['pages'][$id]['slug'] = $newSlug;
                /** @var DataHandler $localDataHandler */
                $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $localDataHandler->start($data, []);
                $localDataHandler->setCorrelationId($dataHandler->getCorrelationId());
                $localDataHandler->process_datamap();
            }
        }
    }

    protected function resolveSubPages(int $id, int $languageUid): array
    {
        // First resolve all sub-pages in default language
        $queryBuilder = $this->getQueryBuilderForTable('pages');
        $subPages = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT))
            )
            ->orderBy('uid', 'ASC')
            ->execute()
            ->fetchAll();

        // if the language is not the default language, resolve the language related records.
        if ($languageUid > 0) {
            $queryBuilder = $this->getQueryBuilderForTable('pages');
            $subPages = $queryBuilder
                ->select('*')
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->in('l10n_parent', $queryBuilder->createNamedParameter(array_column($subPages, 'uid'), Connection::PARAM_INT_ARRAY)),
                    $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, \PDO::PARAM_INT))
                )
                ->orderBy('uid', 'ASC')
                ->execute()
                ->fetchAll();
        }
        $results = [];
        if (!empty($subPages)) {
            $results = $this->pageRepository->getPagesOverlay($subPages, $languageUid);
        }
        return $results;
    }


    /**
     * @param string $table
     * @return QueryBuilder
     */
    protected static function getQueryBuilderForTable($table)
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
    }
}