<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final readonly class RedirectInfoService
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private UriBuilder $uriBuilder,
    ) {
    }

    public function countRedirectsForPage(int $pageUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_redirect');
        $target = 't3://page?uid=' . $pageUid;

        return (int)$queryBuilder
            ->count('*')
            ->from('sys_redirect')
            ->where(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq(
                        'target',
                        $queryBuilder->createNamedParameter($target),
                    ),
                    $queryBuilder->expr()->like(
                        'target',
                        $queryBuilder->createNamedParameter(
                            $queryBuilder->escapeLikeWildcards($target) . '&%',
                        ),
                    ),
                ),
            )
            ->executeQuery()
            ->fetchOne();
    }

    public function buildRedirectsModuleUrl(int $pageUid): string
    {
        return (string)$this->uriBuilder->buildUriFromRoute(
            Typo3Compatibility::getRedirectsModuleRoute(),
            ['demand' => ['target' => 't3://page?uid=' . $pageUid]],
        );
    }

    public function canUserAccessRedirectsModule(): bool
    {
        $backendUser = $this->getBackendUser();

        return $backendUser->check('tables_select', 'sys_redirect')
            && $backendUser->check('modules', Typo3Compatibility::getRedirectsModuleRoute());
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
