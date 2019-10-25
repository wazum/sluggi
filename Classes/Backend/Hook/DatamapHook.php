<?php
declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Hook;

use Doctrine\DBAL\ConnectionException;
use RuntimeException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Helper\Configuration;
use Wazum\Sluggi\Helper\PermissionHelper;
use Wazum\Sluggi\Helper\SlugHelper as SluggiSlugHelper;

/**
 * Class DatamapHook
 *
 * @package Wazum\Sluggi\Backend\Hook
 * @author  Wolfgang Klinger <wolfgang@wazum.com>
 */
class DatamapHook
{
    /**
     * @var Connection
     */
    protected $connection;

    public function __construct()
    {
        $this->connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('pages');
    }

    /**
     * @param array $incomingFieldArray
     * @param string $table
     * @param $id
     * @param DataHandler $dataHandler
     */
    public function processDatamap_preProcessFieldArray(
        /** @noinspection PhpUnusedParameterInspection */
        array &$incomingFieldArray,
        string $table,
        $id,
        DataHandler $dataHandler
    ): void {
        if ($table !== 'pages' || !is_numeric($id)) {
            return;
        }

        // Strip off every trailing slash
        if (!empty($incomingFieldArray['slug']) && $incomingFieldArray['slug'] !== '/') {
            $incomingFieldArray['slug'] = rtrim($incomingFieldArray['slug'], '/');
        }

        $synchronize = (bool)Configuration::get('synchronize');
        // Synchronization happens already via Javascript (Ajax)
        // but if the connection is too slow it could happen,
        // that the save request contains the old slug
        // so we have to do this on the server too
        if ($synchronize) {
            $data = array_merge(BackendUtility::getRecord('pages', $id), $incomingFieldArray);
            if ((bool)$data['tx_sluggi_sync']) {
                $fieldConfig = $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? [];
                /** @var SlugHelper $helper */
                $helper = GeneralUtility::makeInstance(SlugHelper::class, 'pages', 'slug', $fieldConfig);
                $incomingFieldArray['slug'] = $helper->generate($data, $data['pid']);
            }
        }
    }

    /**
     * @param string $status
     * @param string $table
     * @param string|integer $id
     * @param array $fieldArray
     * @param DataHandler $dataHandler
     * @throws SiteNotFoundException
     * @see processDatamap_preProcessFieldArray
     */
    public function processDatamap_postProcessFieldArray(
        /** @noinspection PhpUnusedParameterInspection */
        string $status,
        string $table,
        $id,
        array &$fieldArray,
        DataHandler $dataHandler
    ): void {
        if ($status === 'new' || $table !== 'pages' || empty($fieldArray['slug'])) {
            return;
        }

        $id = (int)$id; // not a new record, so definitely an integer
        $page = BackendUtility::getRecord('pages', $id);
        $languageId = $page['sys_language_uid'];
        $synchronize = (bool)Configuration::get('synchronize');
        // If we synchronized in processDatamap_preProcessFieldArray
        // we don't need to modify the slug here
        if (!$synchronize && !PermissionHelper::hasFullPermission()) {
            $mountRootPage = PermissionHelper::getTopmostAccessiblePage($id);
            $inaccessibleSlugSegments = SluggiSlugHelper::getSlug($mountRootPage['pid'], $languageId);
            $fieldArray['slug'] = $inaccessibleSlugSegments . $fieldArray['slug'];
        }

        $previousSlug = $page['slug'] ?? '';
        try {
            $this->connection->beginTransaction();
            $this->updateRedirect($id, $fieldArray['slug'], $languageId);
            $this->renameRecursively($id, $fieldArray['slug'], $previousSlug, $languageId);
            $this->connection->commit();
        } catch (ConnectionException $e) {
            try {
                $this->connection->rollBack();
            } catch (ConnectionException $e) {
                $this->setFlashMessage($e->getMessage(), FlashMessage::ERROR);
            }
            $this->setFlashMessage($e->getMessage(), FlashMessage::ERROR);
        }
    }

    /**
     * @param string $table
     * @param int $id
     * @param int $targetId
     * @param int $siblingTargetId
     * @param array $moveRecord
     * @param array $updateFields
     * @param DataHandler $dataHandler
     * @throws SiteNotFoundException
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

        $this->updateSlugForMovedPage($id, $targetId);
    }

    /**
     * @param string $table
     * @param int $id
     * @param int $targetId
     * @param array $moveRecord
     * @param array $updateFields
     * @param DataHandler $dataHandler
     * @throws SiteNotFoundException
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

        $this->updateSlugForMovedPage($id, $targetId);
    }

    /**
     * @param int $id
     * @param int $targetId
     * @throws SiteNotFoundException
     */
    protected function updateSlugForMovedPage(int $id, int $targetId): void
    {
        $currentPage = BackendUtility::getRecord('pages', $id, 'uid, slug, sys_language_uid');
        if (!empty($currentPage)) {
            $languageId = $currentPage['sys_language_uid'];
            $currentSlugParts = explode('/', $currentPage['slug']);
            $currentSlugSegment = array_pop($currentSlugParts);
            $targetPage = $this->getPageOrTranslatedPage($targetId, $languageId);
            if (!empty($targetPage)) {
                $newSlug = rtrim($targetPage['slug'], '/') . '/' . $currentSlugSegment;

                try {
                    $this->connection->beginTransaction();
                    $this->updateRedirect($id, $newSlug, $languageId);
                    $this->updateSlug($id, $newSlug);
                    $this->renameRecursively($id, $newSlug, $currentPage['slug'], $languageId);
                    $this->connection->commit();
                } catch (ConnectionException $e) {
                    try {
                        $this->connection->rollBack();
                    } catch (ConnectionException $e) {
                        $this->setFlashMessage($e->getMessage(), FlashMessage::ERROR);
                    }
                    $this->setFlashMessage($e->getMessage(), FlashMessage::ERROR);
                }
            }
        }
    }

    /**
     * @param int $pageId
     * @param string $slug
     * @param int $languageId
     * @throws SiteNotFoundException
     */
    protected function updateRedirect(int $pageId, string $slug, int $languageId): void
    {
        $redirectsActive = (bool)Configuration::get('redirects');
        if (!$redirectsActive || !ExtensionManagementUtility::isLoaded('redirects')) {
            return;
        }

        [$siteHost, $sitePath] = $this->getBaseByPageId($pageId, $languageId);
        $previousSlug = BackendUtility::getRecord('pages', $pageId, 'slug')['slug'] ?? '';
        if (!empty($previousSlug) && $previousSlug !== $slug) {
            // Remove old redirects matching the previous slug
            $this->deleteRedirect($siteHost, $sitePath . $previousSlug);

            $redirectLifetime = strtotime((string)Configuration::get('redirect_lifetime'));
            $redirectHttpStatusCode = (int)Configuration::get('redirect_lifetime');
            $this->connection->insert(
                'sys_redirect',
                [
                    'pid' => 0,
                    'createdon' => time(),
                    'updatedon' => time(),
                    'createdby' => $this->getBackendUserId(),
                    'endtime' => $redirectLifetime !== false ? $redirectLifetime : strtotime('+1 month'),
                    'source_host' => $siteHost,
                    'source_path' => $sitePath . $previousSlug,
                    'target_statuscode' => in_array($redirectHttpStatusCode, [301, 307], true) ? $redirectHttpStatusCode : 307,
                    'target' => 't3://page?uid=' . $pageId
                ]
            );
        }
        // Remove redirects matching the new slug
        $this->deleteRedirect($siteHost, $sitePath . $slug);
    }

    /**
     * @param int $pageId
     * @param string $slug
     */
    protected function updateSlug(int $pageId, string $slug): void
    {
        $this->connection->update(
            'pages',
            ['slug' => $slug],
            ['uid' => $pageId]
        );
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
        $slug = rtrim($slug, '/') . '/';
        $previousSlug = rtrim($previousSlug, '/') . '/';
        if (!empty($previousSlug) && $slug !== $previousSlug) {
            $childPages = $this->getChildPages($pageId, $languageId);
            if (count($childPages)) {
                // Replace slug segments for all child pages recursively
                foreach ($childPages as $page) {
                    $updatedSlug = str_replace($previousSlug, $slug, $page['slug']);
                    $this->updateRedirect((int)$page['uid'], $updatedSlug, $languageId);
                    $this->updateSlug((int)$page['uid'], $updatedSlug);
                    $this->renameChildSlugsAndCreateRedirects((int)$page['uid'], $languageId, $slug, $previousSlug);
                }
            }
        }
    }

    /**
     * @param string $host
     * @param string $path
     */
    private function deleteRedirect(string $host, string $path): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder->delete('sys_redirect')
            ->where(
                $queryBuilder->expr()->eq('source_host', $queryBuilder->createNamedParameter($host)),
                $queryBuilder->expr()->eq('source_path', $queryBuilder->createNamedParameter($path))
            )->execute();
    }

    /**
     * @param int $id
     * @param string $slug
     * @param string $previousSlug
     * @param int $languageId
     * @throws SiteNotFoundException
     */
    protected function renameRecursively(int $id, string $slug, string $previousSlug, int $languageId): void
    {
        $renameRecursively = (bool)Configuration::get('recursively');
        if ($renameRecursively) {
            $this->renameChildSlugsAndCreateRedirects($id, $languageId, $slug, $previousSlug);
        }

        $redirectsActive = (bool)Configuration::get('redirects');
        if ($redirectsActive && ExtensionManagementUtility::isLoaded('redirects')) {
            // Rebuild redirect cache
            GeneralUtility::makeInstance(\TYPO3\CMS\Redirects\Service\RedirectCacheService::class)->rebuild();
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
                $queryBuilder->expr()->eq('pid', $pageId),
                $queryBuilder->expr()->eq('sys_language_uid', $languageId)
            )->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Returns a page record or the translated page record
     * for the given page ID
     *
     * @param int $pageId
     * @param int $languageId
     * @return array|null
     */
    protected function getPageOrTranslatedPage(int $pageId, int $languageId): ?array
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);

        $page = null;
        // Try to find a translated page first, as the given page ID is always
        // in default language
        if ($languageId > 0) {
            $page = $queryBuilder->select('uid', 'slug')
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->eq('l10n_parent', $pageId),
                    $queryBuilder->expr()->eq('sys_language_uid', $languageId)
                )->execute()->fetch(\PDO::FETCH_ASSOC);
        }
        if (empty($page)) {
            $page = BackendUtility::getRecord('pages', $pageId, 'uid, slug');
        }

        return $page;
    }

    /**
     * Returns the site base host and path
     *
     * @param $pageId
     * @param $languageId
     * @return array
     * @throws SiteNotFoundException
     */
    protected function getBaseByPageId($pageId, $languageId): array
    {
        $language = null;
        // Fetch default language page ID to get the site
        if ($languageId > 0) {
            $defaultLanguagePageId = BackendUtility::getRecord('pages', $pageId, 'l10n_parent')['l10n_parent'];
            if ($defaultLanguagePageId > 0) {
                $pageId = $defaultLanguagePageId;
            }
        }
        /** @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($pageId);
        if ($languageId !== null) {
            $language = $site->getLanguageById((int)$languageId);
        }
        if ($language === null) {
            $language = $site->getDefaultLanguage();
        }

        return [
            $site->getBase()->getHost(), // Site host
            rtrim($language->getBase()->getPath(), '/') // Site language path
        ];
    }

    /**
     * @return int
     */
    protected function getBackendUserId(): int
    {
        return $GLOBALS['BE_USER']->user['uid'] ?? 0;
    }

    /**
     * @param string $text
     * @param int $severity
     */
    protected function setFlashMessage(string $text, int $severity): void
    {
        /** @var FlashMessage $message */
        $message = GeneralUtility::makeInstance(FlashMessage::class, $text, '', $severity);
        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->addMessage($message);
    }
}
