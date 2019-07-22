<?php
declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Hook;

use Doctrine\DBAL\ConnectionException;
use PDO;
use RuntimeException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Redirects\Service\RedirectCacheService;
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
     * @throws SiteNotFoundException
     */
    public function processDatamap_postProcessFieldArray(
        /** @noinspection PhpUnusedParameterInspection */
        $status,
        $table,
        $id,
        &$fieldArray,
        DataHandler $dataHandler
    ): void {
        if ($status !== 'new' && $table === 'pages' && !empty($fieldArray['slug'])) {
            $languageId = BackendUtility::getRecord('pages', $id, 'sys_language_uid')['sys_language_uid'] ?? 0;
            if (!PermissionHelper::hasFullPermission()) {
                $mountRootPage = PermissionHelper::getTopmostAccessiblePage($id);
                $inaccessibleSlugSegments = SluggiSlugHelper::getSlug($mountRootPage['pid']);
                $fieldArray['slug'] = $inaccessibleSlugSegments . $fieldArray['slug'];
            }

            $this->updateRedirect($id, $fieldArray['slug'], $languageId);

            $renameRecursively = false;
            try {
                $renameRecursively = (bool)GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('sluggi',
                    'recursively');
            } catch (ExtensionConfigurationExtensionNotConfiguredException $e) {
            } catch (ExtensionConfigurationPathDoesNotExistException $e) {
            }
            if ($renameRecursively) {
                $previousSlug = BackendUtility::getRecord('pages', $id, 'slug')['slug'] ?? '';
                $this->renameChildSlugsAndCreateRedirects($id, $languageId, $fieldArray['slug'], $previousSlug);
            }

            // rebuild redirect cache
            GeneralUtility::makeInstance(RedirectCacheService::class)->rebuild();
        }
    }

    /**
     * @param int $pageId
     * @param int $languageId
     * @param string $slug
     * @param string $previousSlug
     * @throws SiteNotFoundException
     */
    protected function renameChildSlugsAndCreateRedirects(
        int $pageId,
        int $languageId,
        string $slug,
        string $previousSlug
    ): void {
        if (!empty($previousSlug) && $slug !== $previousSlug) {
            $childPages = $this->getChildPages($pageId, $languageId);
            if (count($childPages)) {
                // replace slug segments for all child pages recursively
                foreach ($childPages as $page) {
                    $updatedSlug = str_replace($previousSlug, $slug, $page['slug']);
                    try {
                        $this->connection->beginTransaction();
                        $this->updateRedirect((int)$page['uid'], $updatedSlug, $languageId);
                        $this->updateSlug((int)$page['uid'], $updatedSlug);
                        $this->connection->commit();
                    } catch (ConnectionException $e) {
                    }
                    $this->renameChildSlugsAndCreateRedirects((int)$page['uid'], $languageId, $slug, $previousSlug);
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
     * @param string $slug
     * @param int $languageId
     * @throws SiteNotFoundException
     */
    protected function updateRedirect(int $pageId, string $slug, int $languageId): void
    {
        $siteBase = $this->getSiteBaseByPageId($pageId, $languageId);
        // @todo
        // Remove old redirects matching the new slug
        $this->deleteRedirect($siteBase . $slug);

        $previousSlug = BackendUtility::getRecord('pages', $pageId, 'slug')['slug'] ?? '';
        if (!empty($previousSlug)) {
            // @todo
            // Remove old redirects matching the previous slug
            $this->deleteRedirect($siteBase . $previousSlug);

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
                    'createdby' => $this->getBackendUserId(),
                    'source_host' => '*',
                    'source_path' => $siteBase . $previousSlug,
                    'target_statuscode' => 301,
                    'target' => 't3://page?uid=' . $pageId
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
     * @return int
     */
    protected function getBackendUserId(): int
    {
        /** @var BackendUserAuthentication $BE_USER */
        global $BE_USER;

        return $BE_USER->user['uid'] ?? 0;
    }

    /**
     * @param $pageId
     * @param $languageId
     * @return string
     * @throws SiteNotFoundException
     */
    protected function getSiteBaseByPageId($pageId, $languageId): string
    {
        $language = null;
        /** @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($pageId);
        if ($languageId !== null) {
            $language = $site->getLanguageById((int)$languageId);
        }
        if ($language === null) {
            $language = $site->getDefaultLanguage();
        }
        return rtrim($language->getBase()->getPath(), '/');
    }

    /**
     * @param string $slug
     */
    private function deleteRedirect(string $slug): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_redirect');
        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder->delete('sys_redirect')
            ->where(
                $queryBuilder->expr()->eq('source_path',
                    $queryBuilder->createNamedParameter($slug))
            )->execute();
    }

}
