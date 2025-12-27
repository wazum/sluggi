<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class PreventSelfReferencingRedirectTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    private function setUpSite(): void
    {
        $configuration = [
            'rootPageId' => 1,
            'base' => '/',
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'locale' => 'en_US.UTF-8',
                    'base' => '/',
                ],
            ],
            'settings' => [
                'redirects' => [
                    'autoUpdateSlugs' => true,
                    'autoCreateRedirects' => true,
                ],
            ],
        ];
        Typo3Compatibility::writeSiteConfiguration('test', $configuration);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_redirect_test.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function selfReferencingRedirectIsRemovedWhenSlugChangesBackToOriginal(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['pages' => [2 => ['slug' => '/page-b']]],
            []
        );
        $dataHandler->process_datamap();

        $redirects = $this->getRedirectsForPage();
        self::assertCount(1, $redirects, 'One redirect should exist after first rename');
        self::assertSame('/page-a', $redirects[0]['source_path'], 'Redirect source should be /page-a');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['pages' => [2 => ['slug' => '/page-a']]],
            []
        );
        $dataHandler->process_datamap();

        $allRedirects = $this->getAllRedirects();
        self::assertCount(1, $allRedirects, 'Only one redirect should exist after rename back');
        self::assertSame('/page-b', $allRedirects[0]['source_path'], 'Redirect should be from /page-b');

        $sourcePaths = array_column($allRedirects, 'source_path');
        self::assertNotContains('/page-a', $sourcePaths, 'No redirect from /page-a should exist');
    }

    #[Test]
    public function noSelfReferencingRedirectIsCreated(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['pages' => [2 => ['slug' => '/page-b']]],
            []
        );
        $dataHandler->process_datamap();

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['pages' => [2 => ['slug' => '/page-a']]],
            []
        );
        $dataHandler->process_datamap();

        $allRedirects = $this->getAllRedirects();
        foreach ($allRedirects as $redirect) {
            if ((int)$redirect['deleted'] === 0) {
                self::assertNotSame(
                    '/page-a',
                    $redirect['source_path'],
                    'No active redirect should point FROM the current slug'
                );
            }
        }
    }

    #[Test]
    public function validRedirectsArePreservedWhenNoSelfReference(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['pages' => [2 => ['slug' => '/page-b']]],
            []
        );
        $dataHandler->process_datamap();

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['pages' => [2 => ['slug' => '/page-c']]],
            []
        );
        $dataHandler->process_datamap();

        $redirects = $this->getRedirectsForPage();
        $sourcePaths = array_column($redirects, 'source_path');

        self::assertContains('/page-a', $sourcePaths, 'Redirect from /page-a should still exist');
        self::assertContains('/page-b', $sourcePaths, 'Redirect from /page-b should exist');
        self::assertNotContains('/page-c', $sourcePaths, 'No self-referencing redirect from /page-c');
    }

    #[Test]
    public function redirectsForOtherSitesAreNotDeletedWhenSlugChanges(): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_redirect');
        $connection->insert('sys_redirect', [
            'source_host' => 'other-site.example.com',
            'source_path' => '/page-b',
            'target' => '/some-target',
            'target_statuscode' => 301,
        ]);
        $otherSiteRedirectUid = (int)$connection->lastInsertId();

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['pages' => [2 => ['slug' => '/page-b']]],
            []
        );
        $dataHandler->process_datamap();

        $redirects = $this->getAllRedirects();
        $otherSiteRedirect = array_filter(
            $redirects,
            static fn (array $r) => (int)$r['uid'] === $otherSiteRedirectUid
        );

        self::assertCount(1, $otherSiteRedirect, 'Redirect for other site should still exist');
        self::assertSame(
            'other-site.example.com',
            array_values($otherSiteRedirect)[0]['source_host'],
            'Other site redirect should have its original source_host'
        );
    }

    private function getRedirectsForPage(): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_redirect');

        return $connection->select(
            ['*'],
            'sys_redirect',
            ['deleted' => 0]
        )->fetchAllAssociative();
    }

    private function getDeletedRedirects(): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_redirect');

        return $connection->select(
            ['*'],
            'sys_redirect',
            ['deleted' => 1]
        )->fetchAllAssociative();
    }

    private function getAllRedirects(): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_redirect');

        return $connection->select(
            ['*'],
            'sys_redirect',
            []
        )->fetchAllAssociative();
    }
}
