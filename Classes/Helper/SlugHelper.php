<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Helper;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function array_pop;
use function explode;

/**
 * Class SlugHelper
 *
 * @package Wazum\Sluggi\Helper
 * @author  Wolfgang Klinger <wolfgang@wazum.com>
 */
class SlugHelper
{
    /**
     * Return correct slug path in case a short slug - not matching the page tree - is used
     */
    public static function getSlugPath($pageRecord) {
        // if we have a first level slug (aka short url), we need no parent path info
        if (strlen($pageRecord['slug']) < 2 || substr_count($pageRecord['slug'], '/', 1) === 0) {
            return '';
        }
        /** @var \TYPO3\CMS\Core\DataHandling\SlugHelper $slugHelper */
        $slugHelper = GeneralUtility::makeInstance(\TYPO3\CMS\Core\DataHandling\SlugHelper::class, 'pages', 'slug', $GLOBALS['TCA']['pages']['columns']['slug']['config']);
        $inaccessibleSlugSegments = $slugHelper->generate($pageRecord, $pageRecord['pid']);
        // chop off part for current page
        return substr($inaccessibleSlugSegments,0,strrpos($inaccessibleSlugSegments,'/'));
    }

    /**
     * Return slug for given page ID
     */
    public static function getSlug(int $pageId, int $languageId = 0): string
    {
        $rootLine = BackendUtility::BEgetRootLine($pageId, '', true, ['nav_title']);
        do {
            $pageRecord = array_shift($rootLine);
            // Exclude spacers, recyclers, folders and everything else which has no slug
        } while (!empty($rootLine) && (int)$pageRecord['doktype'] >= 199);

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);

        $slug = '';
        if ($pageRecord['uid'] > 0) {
            $pageUid = $pageRecord['uid'];
            if ($languageId > 0) {
                $pageUid = (int)$queryBuilder->select('uid')
                    ->from('pages')
                    ->where(
                        $queryBuilder->expr()->eq('l10n_parent', $pageUid),
                        $queryBuilder->expr()->eq('sys_language_uid', $languageId)
                    )->execute()->fetchColumn();
            }

            if ($pageUid === 0) {
                $pageUid = $pageRecord['uid'];
            }

            $slug = (string)$queryBuilder->select('slug')
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->in('uid', $pageUid)
                )->execute()->fetchColumn();
        }

        return $slug === '/' ? '' : $slug;
    }

    public static function getLastSlugSegment(string $slug): string
    {
        $parts = explode('/', $slug);

        return '/' . array_pop($parts);
    }
}
