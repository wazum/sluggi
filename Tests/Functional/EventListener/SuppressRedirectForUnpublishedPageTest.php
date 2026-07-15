<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class SuppressRedirectForUnpublishedPageTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    #[Test]
    public function hiddenPageSlugRenameDoesNotCreateRedirect(): void
    {
        $this->saveFields(2, ['slug' => '/draft-b']);

        self::assertSame(0, $this->countAllRedirects());
    }

    #[Test]
    public function visiblePageSlugRenameStillCreatesRedirect(): void
    {
        $this->saveFields(3, ['slug' => '/live-b']);

        $redirects = $this->fetchAllRedirects();
        self::assertCount(1, $redirects);
        self::assertSame('/live-a', $redirects[0]['source_path']);
    }

    #[Test]
    public function futureStarttimePageSlugRenameDoesNotCreateRedirect(): void
    {
        $this->saveFields(7, ['slug' => '/scheduled-b']);

        self::assertSame(0, $this->countAllRedirects());
    }

    #[Test]
    public function expiredEndtimePageSlugRenameDoesNotCreateRedirect(): void
    {
        $this->saveFields(8, ['slug' => '/expired-b']);

        self::assertSame(0, $this->countAllRedirects());
    }

    #[Test]
    public function publishAndRenameInSameSaveDoesNotCreateRedirect(): void
    {
        $this->saveFields(4, ['slug' => '/live-d', 'hidden' => 0]);

        self::assertSame(0, $this->countAllRedirects());
    }

    #[Test]
    public function cascadeFromHiddenParentToVisibleChildCreatesChildRedirectButNotParent(): void
    {
        $this->saveFields(5, ['slug' => '/hidden-parent-renamed']);

        $redirects = $this->fetchAllRedirects();
        $sourcePaths = array_column($redirects, 'source_path');
        self::assertNotContains('/hidden-parent', $sourcePaths, 'Hidden parent must not get its own redirect.');
        self::assertContains('/hidden-parent/visible-child', $sourcePaths, 'Visible child must get a cascaded redirect.');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_unpublished_redirect_test.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    private function setUpSite(): void
    {
        Typo3Compatibility::writeSiteConfiguration('test', [
            'rootPageId' => 1,
            'base' => '/',
            'languages' => [[
                'languageId' => 0,
                'title' => 'English',
                'locale' => 'en_US.UTF-8',
                'base' => '/',
            ]],
            'settings' => [
                'redirects' => [
                    'autoUpdateSlugs' => true,
                    'autoCreateRedirects' => true,
                ],
            ],
        ]);
    }

    private function saveFields(int $pageId, array $fields): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [$pageId => $fields]], []);
        $dataHandler->process_datamap();
    }

    private function countAllRedirects(): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable('sys_redirect')
            ->count('*', 'sys_redirect', []);
    }

    private function fetchAllRedirects(): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_redirect');
        $queryBuilder->getRestrictions()->removeAll();

        return array_values(
            $queryBuilder
                ->select('*')
                ->from('sys_redirect')
                ->executeQuery()
                ->fetchAllAssociative()
        );
    }
}
