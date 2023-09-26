<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Helper;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class SlugHelper
{
    public static function getSlug(int $pageId, int $languageId = 0): string
    {
        $excludedPageTypes = GeneralUtility::intExplode(',', Configuration::get('exclude_page_types') ?? '', true);
        $rootLine = BackendUtility::BEgetRootLine($pageId, '', true);
        // Exclude pages with an excluded page type by configuration
        do {
            $pageRecord = \array_shift($rootLine);
        } while (!empty($rootLine) && in_array((int) $pageRecord['doktype'], $excludedPageTypes, true));

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);

        $slug = '';
        if ($pageRecord && $pageRecord['uid'] > 0) {
            $pageUid = $pageRecord['uid'];
            if ($languageId > 0) {
                $pageUid = (int) $queryBuilder->select('uid')
                    ->from('pages')
                    ->where(
                        $queryBuilder->expr()->eq('l10n_parent', $pageUid),
                        $queryBuilder->expr()->eq('sys_language_uid', $languageId)
                    )->execute()->fetchOne();
            }

            if (0 === $pageUid) {
                $pageUid = $pageRecord['uid'];
            }

            $slug = (string) $queryBuilder->select('slug')
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->in('uid', $pageUid)
                )->execute()->fetchOne();
        }

        return '/' === $slug ? '' : $slug;
    }

    public static function getLastSlugSegment(string $slug): string
    {
        $parts = \explode('/', $slug);

        return '/' . \array_pop($parts);
    }
}
