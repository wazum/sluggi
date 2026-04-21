<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class ReservedSlugPathTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'sluggi' => [
                'synchronize' => '1',
            ],
        ],
    ];

    /**
     * @param list<string> $reservedPaths
     */
    private function setUpSite(array $reservedPaths = ['/api']): void
    {
        Typo3Compatibility::writeSiteConfiguration('test', [
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
                'sluggi' => [
                    'reservedPaths' => $reservedPaths,
                ],
            ],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_reserved_path.csv');
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        $this->setUpSite();
    }

    private function getSlug(int $uid): string
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $qb->getRestrictions()->removeAll();
        $row = $qb->select('slug')
            ->from('pages')
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, \Doctrine\DBAL\ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        return (string)($row['slug'] ?? '');
    }

    #[Test]
    public function editExistingPageToReservedSlugIsRejected(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [2 => ['slug' => '/api/v1']]], []);
        $dataHandler->process_datamap();

        self::assertSame('/about', $this->getSlug(2), 'Slug must stay unchanged');
    }

    #[Test]
    public function editExistingPageToAcceptableSlugSucceeds(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [2 => ['slug' => '/about-us']]], []);
        $dataHandler->process_datamap();

        self::assertSame('/about-us', $this->getSlug(2));
    }

    #[Test]
    public function editExistingPageWithUnchangedSlugStillSucceedsWhenSlugIsGrandfathered(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [3 => ['title' => 'API Documentation', 'slug' => '/api']]], []);
        $dataHandler->process_datamap();

        $row = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('pages')
            ->select(['title', 'slug'], 'pages', ['uid' => 3])
            ->fetchAssociative();

        self::assertSame('API Documentation', (string)$row['title']);
        self::assertSame('/api', (string)$row['slug']);
    }

    #[Test]
    public function createNewPageWithReservedSlugClearsTheSlug(): void
    {
        // A new top-level page submits /api/foo as its slug. The slug field
        // is cleared so TYPO3 core falls back to a title-derived default.
        // The record is still created (so the editor keeps title, nav_title,
        // subpage config, etc.) but the stored slug is never /api/foo, and
        // an error is logged so the editor can set a non-reserved slug.
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['pages' => ['NEW1' => ['pid' => 1, 'title' => 'New API', 'slug' => '/api/foo', 'doktype' => 1]]],
            [],
        );
        $dataHandler->process_datamap();

        $uid = (int)($dataHandler->substNEWwithIDs['NEW1'] ?? 0);
        self::assertGreaterThan(0, $uid, 'Record is still created so the editor keeps the rest of their form data');
        self::assertNotSame('/api/foo', $this->getSlug($uid), 'Reserved slug must not be persisted');
        self::assertNotEmpty($dataHandler->errorLog, 'Error must be logged so the editor is notified');
    }

    #[Test]
    public function titleChangeWithSyncOnThatRegeneratesReservedSlugIsRejected(): void
    {
        // Reserve /admin on this site (no conflicting page in fixture), so
        // regenerating from title "Admin" yields /admin unchanged by uniqueness.
        $this->setUpSite(['/admin']);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [2 => ['title' => 'Admin']]], []);
        $dataHandler->process_datamap();

        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $qb->getRestrictions()->removeAll();
        $row = $qb->select('title', 'slug')
            ->from('pages')
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter(2, \Doctrine\DBAL\ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        self::assertSame('Admin', (string)$row['title'], 'Title must be saved');
        self::assertSame('/about', (string)$row['slug'], 'Slug must stay at the previous value');
    }

    #[Test]
    public function titleChangeOnParentPageDoesNotCascadeChildrenWhenParentRegeneratedSlugIsReserved(): void
    {
        // Parent page 4 (/foo) with sync=1, child page 5 (/foo/child). Reserving
        // /admin means a title change on page 4 to "Admin" would regenerate the
        // parent slug to /admin and cascade children to /admin/child. Both must
        // be blocked.
        $this->setUpSite(['/admin']);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [4 => ['title' => 'Admin']]], []);
        $dataHandler->process_datamap();

        self::assertSame('/foo', $this->getSlug(4), 'Parent slug must not change');
        self::assertSame('/foo/child', $this->getSlug(5), 'Child slug must not cascade');
    }

    #[Test]
    public function reservedPathsAreScopedPerSite(): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('pages');
        $connection->insert('pages', [
            'uid' => 100,
            'pid' => 0,
            'title' => 'Root B',
            'slug' => '/',
            'doktype' => 1,
            'tx_sluggi_sync' => 0,
        ]);
        $connection->insert('pages', [
            'uid' => 101,
            'pid' => 100,
            'title' => 'About B',
            'slug' => '/about-b',
            'doktype' => 1,
            'tx_sluggi_sync' => 1,
        ]);

        Typo3Compatibility::writeSiteConfiguration('site-b', [
            'rootPageId' => 100,
            'base' => '/',
            'languages' => [[
                'languageId' => 0,
                'title' => 'English',
                'locale' => 'en_US.UTF-8',
                'base' => '/',
            ]],
            'settings' => [
                'sluggi' => [
                    'reservedPaths' => ['/admin'],
                ],
            ],
        ]);

        // Site "test" reserves /api. Saving /admin on page 2 (site test)
        // should succeed because /admin is not reserved on that site.
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [2 => ['slug' => '/admin']]], []);
        $dataHandler->process_datamap();

        self::assertSame('/admin', $this->getSlug(2), 'Site "test" must allow /admin');

        // Conversely, saving /api/v1 on page 101 (site-b) should succeed.
        $dataHandler2 = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler2->start(['pages' => [101 => ['slug' => '/api/v1']]], []);
        $dataHandler2->process_datamap();

        self::assertSame('/api/v1', $this->getSlug(101), 'Site-b must allow /api/v1');
    }

    #[Test]
    public function nestedSlugCascadeDoesNotTriggerReservedPathErrorLog(): void
    {
        // In cascade flow (correlation id set by SlugService), the reserved-path
        // hook must short-circuit. We verify that by asserting no reserved-path
        // error was added to the DataHandler log, even for a slug under /api.
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $correlationId = \TYPO3\CMS\Core\DataHandling\Model\CorrelationId::forScope('test')
            ->withAspects(
                \TYPO3\CMS\Redirects\Service\SlugService::CORRELATION_ID_IDENTIFIER,
                'slug',
            );
        $dataHandler->start(['pages' => [5 => ['slug' => '/api/child']]], []);
        $dataHandler->setCorrelationId($correlationId);
        $dataHandler->process_datamap();

        $reservedErrors = array_filter(
            $dataHandler->errorLog,
            static fn (string $message): bool => str_contains($message, 'reserved URL path') || str_contains($message, 'URL path is reserved'),
        );
        self::assertSame([], $reservedErrors, 'Reserved-path hook must not emit errors during cascade');
    }
}
