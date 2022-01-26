<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Helper;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function array_pop;
use function array_shift;
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
     * Return slug for given page ID
     */
    public static function getSlug(int $pageId, int $languageId = 0): string
    {
        $rootLine = BackendUtility::BEgetRootLine($pageId, '', true, ['nav_title']);
        do {
            $pageRecord = array_shift($rootLine);
            // Exclude spacers, recyclers, folders and everything else which has no slug
        } while (!empty($rootLine) && (int) $pageRecord['doktype'] >= 199);

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);

        $slug = '';
        if ($pageRecord['uid'] > 0) {
            $pageUid = $pageRecord['uid'];
            if ($languageId > 0) {
                $pageUid = (int) $queryBuilder->select('uid')
                    ->from('pages')
                    ->where(
                        $queryBuilder->expr()->eq('l10n_parent', $pageUid),
                        $queryBuilder->expr()->eq('sys_language_uid', $languageId)
                    )->execute()->fetchOne();
            }

            if ($pageUid === 0) {
                $pageUid = $pageRecord['uid'];
            }

            $slug = (string) $queryBuilder->select('slug')
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
