<?php
declare(strict_types=1);

namespace Wazum\Sluggi\Helper;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class SlugHelper
 * @package Wazum\Sluggi\Helper
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class SlugHelper
{

    /**
     * Return slug for given page ID
     *
     * @param int $pageId
     * @return string
     */
    public static function getSlug(int $pageId): string
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');

        return (string)$queryBuilder->select('slug')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->in('uid', $pageId)
            )->execute()->fetchColumn();
    }

}
