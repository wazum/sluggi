<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Service;

use Exception;
use PDO;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Redirects\Service\RedirectCacheService;
use function rtrim;

/**
 * Class SlugService
 *
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class SlugService extends \TYPO3\CMS\Redirects\Service\SlugService
{
    /**
     * @var int
     */
    protected $redirectForceHttps;

    /**
     * @var int
     */
    protected $redirectRespectQueryParameters;

    /**
     * @var int
     */
    protected $redirectKeepQueryParameters;

    protected function initializeSettings(int $pageId): void
    {
        parent::initializeSettings($pageId);

        $settings = $this->site->getConfiguration()['settings']['redirects'] ?? [];
        $this->redirectForceHttps = (int)($settings['forceHttps'] ?? 0);
        $this->redirectRespectQueryParameters = (int)($settings['respectQueryParameters'] ?? 0);
        $this->redirectKeepQueryParameters = (int)($settings['keepQueryParameters'] ?? 0);
    }

    public function rebuildSlugsForSlugChange(
        int $pageId,
        string $currentSlug,
        string $newSlug,
        CorrelationId $correlationId
    ): void {
        try {
            $this->initializeSettings($pageId);
        } catch(SiteNotFoundException $e)
        {
            // Without a site configuration no settings, so we can't do anything here
            return;
        }

        $currentPageRecord = BackendUtility::getRecord('pages', $pageId);
        if ($currentPageRecord === null) {
            return;
        }
        if ($this->autoUpdateSlugs || $this->autoCreateRedirects) {
            $this->createCorrelationIds($pageId, $correlationId);
            if ($this->autoCreateRedirects) {
                $this->createRedirectWithPageId(
                    $currentSlug,
                    $newSlug,
                    (int)$currentPageRecord['sys_language_uid'],
                    (int)$currentPageRecord['uid']
                );
            }
            if ($this->autoUpdateSlugs) {
                $this->checkSubPages($currentPageRecord, $currentSlug, $newSlug);
            }
            $notificationsDisabled = (bool)Configuration::get('disable_notifications');
            if( !$notificationsDisabled || $GLOBALS['BE_USER']->isAdmin()) {
                $this->sendNotification();
            }

            GeneralUtility::makeInstance(RedirectCacheService::class)->rebuild();
        }
    }

    protected function checkSubPages(
        array $currentPageRecord,
        string $oldSlugOfParentPage,
        string $newSlugOfParentPage
    ): void {
        $languageUid = (int)$currentPageRecord['sys_language_uid'];
        // resolveSubPages needs the page id of the default language
        $pageId = $languageUid === 0 ? (int)$currentPageRecord['uid'] : (int)$currentPageRecord['l10n_parent'];
        $subPageRecords = $this->resolveSubPages($pageId, $languageUid);
        foreach ($subPageRecords as $subPageRecord) {
            $newSlug = $this->updateSlug($subPageRecord, $oldSlugOfParentPage, $newSlugOfParentPage);
            if ($newSlug !== null && $this->autoCreateRedirects) {
                $this->createRedirectWithPageId(
                    $subPageRecord['slug'],
                    $newSlug,
                    $languageUid,
                    (int)$subPageRecord['uid'],
                    true
                );
            }
        }
    }

    protected function createRedirectWithPageId(
        string $originalSlug,
        string $newSlug,
        int $languageId,
        int $pageId,
        bool $isSubPage = false
    ): void {
        try {
            $basePath = rtrim($this->site->getLanguageById($languageId)->getBase()->getPath(), '/');
        } catch (Exception $e) {
            return;
        }

        // Fetch possible route enhancer extension (PageTypeSuffix)
        $variant = $this->getVariant($basePath, $isSubPage ? $newSlug : $originalSlug, $languageId, $pageId);

        /** @var DateTimeAspect $date */
        $date = $this->context->getAspect('date');
        $endtime = $date->getDateTime()->modify('+' . $this->redirectTTL . ' days');

        $targetPath = $basePath . $newSlug . ($variant ?? '');
        $sourceHost = $this->site->getBase()->getHost() ?: '*';
        $sourcePath = $basePath . $originalSlug . ($variant ?? '');

        // Delete redirects with this new slug, as this would lead to endless redirect recursion
        $this->deleteRedirect($targetPath, $sourceHost);

        $record = [
            'pid' => 0,
            'updatedon' => $date->get('timestamp'),
            'createdon' => $date->get('timestamp'),
            'createdby' => $this->context->getPropertyFromAspect('backend.user', 'id'),
            'deleted' => 0,
            'disabled' => 0,
            'starttime' => 0,
            'endtime' => $this->redirectTTL > 0 ? $endtime->getTimestamp() : 0,
            'source_host' => $sourceHost,
            'source_path' => $basePath . $originalSlug . ($variant ?? ''),
            'is_regexp' => 0,
            'force_https' => $this->redirectForceHttps,
            'respect_query_parameters' => $this->redirectRespectQueryParameters,
            'keep_query_parameters' => $this->redirectKeepQueryParameters,
            'target' => $targetPath,
            'target_statuscode' => $this->httpStatusCode,
            'hitcount' => 0,
            'lasthiton' => 0,
            'disable_hitcount' => 0,
        ];
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_redirect');

        if (null !== ($existingRecord = $this->getRedirectRecord($sourcePath, $sourceHost))) {
            $record = array_merge($existingRecord, $record);
            $connection->update('sys_redirect', $record, ['uid' => $record['uid']]);
        } else {
            $connection->insert('sys_redirect', $record);
            $id = (int)$connection->lastInsertId('sys_redirect');
            $record['uid'] = $id;
        }

        $this->getRecordHistoryStore()->addRecord(
            'sys_redirect',
            $record['uid'],
            $record,
            $this->correlationIdRedirectCreation
        );
    }

    protected function deleteRedirect(string $sourcePath, string $sourceHost): void
    {
        if (null !== ($record = $this->getRedirectRecord($sourcePath, $sourceHost))) {
            /** @var DateTimeAspect $date */
            $date = $this->context->getAspect('date');
            $record = array_merge($record, [
                'deleted' => 1,
                'updatedon' => $date->get('timestamp'),
            ]);
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('sys_redirect');
            $connection->update('sys_redirect', $record, ['uid' => $record['uid']]);
            $this->getRecordHistoryStore()->addRecord('sys_redirect', $record['uid'], $record,
                $this->correlationIdRedirectCreation);
        }
    }

    protected function getRedirectRecord(string $sourcePath, string $sourceHost): ?array
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_redirect');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $record = $queryBuilder
            ->select('*')
            ->from('sys_redirect')
            ->where(
                $queryBuilder->expr()->eq('source_host', $queryBuilder->createNamedParameter($sourceHost)),
                $queryBuilder->expr()->eq('source_path', $queryBuilder->createNamedParameter($sourcePath))
            )->execute()->fetch(PDO::FETCH_ASSOC);

        return false !== $record ? $record : null;
    }

    protected function getVariant(string $basePath, string $slug, int $languageId, int $pageId): ?string
    {
        // Check for possibly different URL (e.g. with /index.html appended)
        $pageRouter = GeneralUtility::makeInstance(PageRouter::class, $this->site);
        try {
            $generatedPath = $pageRouter->generateUri($pageId, ['_language' => $languageId])->getPath();

            if (!empty($basePath) && !empty($generatedPath)) {
                $generatedPath = preg_replace('/^' . preg_quote($basePath, '/') . '/', '', $generatedPath);
            }
        } catch (InvalidRouteArgumentsException $e) {
            $generatedPath = '';
        }
        $variant = null;
        // There must be some kind of route enhancer involved
        if (($generatedPath !== $slug) && strpos($generatedPath, $slug) !== false) {
            $variant = str_replace($slug, '', $generatedPath);
        }
        if ($variant === $basePath) {
            $variant = null;
        }

        return $variant;
    }
}
