<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Service;

use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Http\Uri;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;
use Wazum\Sluggi\Service\RedirectInfoService;

final class RedirectInfoServiceTest extends TestCase
{
    #[Test]
    public function countRedirectsForPageReturnsCountFromDatabase(): void
    {
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('target = :param1');
        $expressionBuilder->method('like')->willReturn('target LIKE :param2');
        $expressionBuilder->method('or')->willReturn($this->createMock(CompositeExpression::class));

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('createNamedParameter')->willReturn(':bound');
        $queryBuilder->method('escapeLikeWildcards')->willReturnArgument(0);
        $queryBuilder->method('count')->with('*')->willReturn($queryBuilder);
        $queryBuilder->method('from')->with('sys_redirect')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);

        $result = $this->createMock(Result::class);
        $result->method('fetchOne')->willReturn(3);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')
            ->with('sys_redirect')
            ->willReturn($queryBuilder);

        $subject = new RedirectInfoService(
            $connectionPool,
            $this->createMock(UriBuilder::class),
        );

        self::assertSame(3, $subject->countRedirectsForPage(5));
    }

    #[Test]
    public function countRedirectsForPageReturnsZeroWhenNoRedirects(): void
    {
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('target = :param1');
        $expressionBuilder->method('like')->willReturn('target LIKE :param2');
        $expressionBuilder->method('or')->willReturn($this->createMock(CompositeExpression::class));

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('createNamedParameter')->willReturn(':bound');
        $queryBuilder->method('escapeLikeWildcards')->willReturnArgument(0);
        $queryBuilder->method('count')->with('*')->willReturn($queryBuilder);
        $queryBuilder->method('from')->with('sys_redirect')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);

        $result = $this->createMock(Result::class);
        $result->method('fetchOne')->willReturn(0);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')
            ->with('sys_redirect')
            ->willReturn($queryBuilder);

        $subject = new RedirectInfoService(
            $connectionPool,
            $this->createMock(UriBuilder::class),
        );

        self::assertSame(0, $subject->countRedirectsForPage(42));
    }

    #[Test]
    public function buildRedirectsModuleUrlPassesCorrectRouteAndParameters(): void
    {
        $route = Typo3Compatibility::getRedirectsModuleRoute();

        $uriBuilder = $this->createMock(UriBuilder::class);
        $uriBuilder->expects(self::once())
            ->method('buildUriFromRoute')
            ->with($route, ['demand' => ['target' => 't3://page?uid=7']])
            ->willReturn(new Uri('/typo3/module/link-management/redirects?demand%5Btarget%5D=t3%3A%2F%2Fpage%3Fuid%3D7'));

        $subject = new RedirectInfoService(
            $this->createMock(ConnectionPool::class),
            $uriBuilder,
        );

        $result = $subject->buildRedirectsModuleUrl(7);

        self::assertSame('/typo3/module/link-management/redirects?demand%5Btarget%5D=t3%3A%2F%2Fpage%3Fuid%3D7', $result);
    }

    #[Test]
    public function canUserAccessRedirectsModuleReturnsTrueWhenUserHasTableAndModulePermission(): void
    {
        $route = Typo3Compatibility::getRedirectsModuleRoute();

        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('check')
            ->willReturnCallback(static fn (string $type, string $value): bool => match ([$type, $value]) {
                ['tables_select', 'sys_redirect'] => true,
                ['modules', $route] => true,
                default => false,
            });
        $GLOBALS['BE_USER'] = $backendUser;

        $subject = new RedirectInfoService(
            $this->createMock(ConnectionPool::class),
            $this->createMock(UriBuilder::class),
        );

        self::assertTrue($subject->canUserAccessRedirectsModule());

        unset($GLOBALS['BE_USER']);
    }

    #[Test]
    public function canUserAccessRedirectsModuleReturnsFalseWhenUserLacksTablePermission(): void
    {
        $route = Typo3Compatibility::getRedirectsModuleRoute();

        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('check')
            ->willReturnCallback(static fn (string $type, string $value): bool => match ([$type, $value]) {
                ['tables_select', 'sys_redirect'] => false,
                ['modules', $route] => true,
                default => false,
            });
        $GLOBALS['BE_USER'] = $backendUser;

        $subject = new RedirectInfoService(
            $this->createMock(ConnectionPool::class),
            $this->createMock(UriBuilder::class),
        );

        self::assertFalse($subject->canUserAccessRedirectsModule());

        unset($GLOBALS['BE_USER']);
    }

    #[Test]
    public function canUserAccessRedirectsModuleReturnsFalseWhenUserLacksModulePermission(): void
    {
        $route = Typo3Compatibility::getRedirectsModuleRoute();

        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('check')
            ->willReturnCallback(static fn (string $type, string $value): bool => match ([$type, $value]) {
                ['tables_select', 'sys_redirect'] => true,
                ['modules', $route] => false,
                default => false,
            });
        $GLOBALS['BE_USER'] = $backendUser;

        $subject = new RedirectInfoService(
            $this->createMock(ConnectionPool::class),
            $this->createMock(UriBuilder::class),
        );

        self::assertFalse($subject->canUserAccessRedirectsModule());

        unset($GLOBALS['BE_USER']);
    }
}
