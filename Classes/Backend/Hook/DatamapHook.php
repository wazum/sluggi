<?php
declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Hook;

use Doctrine\DBAL\ConnectionException;
use PDO;
use RuntimeException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheGroupException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Helper\PermissionHelper;
use Wazum\Sluggi\Helper\SlugHelper as SluggiSlugHelper;

/**
 * Class DatamapHook
 * @package Wazum\Sluggi\Backend\Hook
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class DatamapHook
{

    /**
     * @var Connection
     */
    protected $connection;

    public function __construct()
    {
        $this->connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('pages');
    }

    /**
     * @param string $status
     * @param string $table
     * @param integer $id
     * @param array $fieldArray
     * @param DataHandler $dataHandler
     */
    public function processDatamap_postProcessFieldArray(
        /** @noinspection PhpUnusedParameterInspection */
        $status,
        $table,
        $id,
        &$fieldArray,
        DataHandler $dataHandler
    ): void {
        if ($table === 'pages' && !empty($fieldArray['slug'])) {
            $languageId = BackendUtility::getRecord('pages', $id, 'sys_language_uid')['sys_language_uid'] ?? 0;
            if (!PermissionHelper::hasFullPermission()) {
                $mountRootPage = PermissionHelper::getTopmostAccessiblePage($id);
                $inaccessibleSlugSegments = SluggiSlugHelper::getSlug($mountRootPage['pid']);
                $fieldArray['slug'] = $inaccessibleSlugSegments . $fieldArray['slug'];
            }

            $this->createRedirect($id);
            $oldSlug = BackendUtility::getRecord('pages', $id, 'slug')['slug'] ?? '';
            $this->renameChildSlugsAndCreateRedirects($id, $languageId, $fieldArray['slug'], $oldSlug);

            // clear redirect cache
            /** @var CacheManager $cacheManager */
            $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
            try {
                $cacheManager->flushCachesInGroupByTags('pages', ['redirects']);
            } catch (NoSuchCacheGroupException $e) {
            }
        }
    }

    /**
     * @param int $pageId
     * @param int $languageId
     * @param string $slug
     * @param string $oldSlug
     */
    protected function renameChildSlugsAndCreateRedirects(
        int $pageId,
        int $languageId,
        string $slug,
        string $oldSlug
    ): void {
        if (!empty($oldSlug) && $slug !== $oldSlug) {
            $childPages = $this->getChildPages($pageId, $languageId);
            if (count($childPages)) {
                // replace slug segments for all child pages recursively
                foreach ($childPages as $page) {
                    try {
                        $this->connection->beginTransaction();
                        $this->createRedirect((int)$page['uid']);
                        $this->updateSlug((int)$page['uid'], str_replace($oldSlug, $slug, $page['slug']));
                        $this->connection->commit();
                    } catch (ConnectionException $e) {
                    }
                    $this->renameChildSlugsAndCreateRedirects((int)$page['uid'], $languageId, $slug, $oldSlug);
                }
            }
        }
    }

    /**
     * @param int $pageId
     * @param string $slug
     */
    protected function updateSlug(int $pageId, string $slug): void
    {
        $this->connection->update('pages',
            ['slug' => $slug],
            ['uid' => $pageId]
        );
    }

    /**
     * @param int $pageId
     */
    protected function createRedirect(int $pageId): void
    {
        $oldSlug = BackendUtility::getRecord('pages', $pageId, 'slug')['slug'] ?? null;
        if ($oldSlug) {
            $this->connection->insert('sys_redirect',
                [
                    // the redirect does not work currently
                    // when an endtime or respect/keep query parameters
                    // is set (core bug?)

                    // 'respect_query_parameters' => 1,
                    // 'keep_query_parameters' => 1,
                    // 'endtime' => strtotime('+1 month')

                    'pid' => 0,
                    'createdon' => time(),
                    'updatedon' => time(),
                    'createdby' => (int)$this->getBackendUser()->id,
                    'source_host' => '*',
                    'source_path' => $oldSlug,
                    'target_statuscode' => 301,
                    'target' => 't3://page?uid=' . $pageId,
                ]);
        }
    }

    /**
     * @param int $pageId
     * @param int $languageId
     * @return array
     * @throws RuntimeException
     */
    protected function getChildPages(int $pageId, int $languageId): array
    {
        $field = 'pid';
        if ($languageId > 0) {
            $pageId = BackendUtility::getRecord('pages', $pageId, 'l10n_parent')['l10n_parent'] ?? null;
            if ($pageId === null) {
                throw new RuntimeException(sprintf('No l10n_parent set for page "%d"', $pageId));
            }
        }
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);

        return $queryBuilder->select('uid', 'slug')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->in($field, $pageId),
                $queryBuilder->expr()->in('sys_language_uid', $languageId)
            )->execute()->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

}
