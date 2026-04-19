<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Service\SlugSyncService;

final class PersistRecordSyncStateTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
        __DIR__ . '/../Fixtures/Extensions/test_sluggi_records',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'sluggi' => [
                'synchronize' => '1',
                'synchronize_tables' => 'tx_sluggitest_article',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/test_records.csv');
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function syncStateCanBePersistedAndReadForExistingRecord(): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable('tx_sluggi_record_sync');

        $connection->insert('tx_sluggi_record_sync', [
            'tablename' => 'tx_sluggitest_article',
            'record_uid' => 1,
            'is_synced' => 1,
        ]);

        $row = $connection->select(
            ['is_synced'],
            'tx_sluggi_record_sync',
            ['tablename' => 'tx_sluggitest_article', 'record_uid' => 1]
        )->fetchAssociative();

        self::assertIsArray($row);
        self::assertSame(1, (int)$row['is_synced']);
    }

    #[Test]
    public function slugIsNotRegeneratedWhenSyncIsToggledOffInSameSave(): void
    {
        // Handshake between PersistRecordSyncState (preProcess) and HandleRecordUpdate
        // (postProcess): changing the title and toggling sync off in one save must not
        // regenerate the slug — the pending override has to cross the phase boundary.
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'tx_sluggitest_article' => [
                    1 => [
                        'title' => 'Updated Title',
                        'tx_sluggi_sync' => 0,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $articleConnection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_sluggitest_article');
        $row = $articleConnection->select(['title', 'slug'], 'tx_sluggitest_article', ['uid' => 1])
            ->fetchAssociative();

        self::assertIsArray($row);
        self::assertSame('Updated Title', (string)$row['title'], 'title change should still be saved');
        self::assertSame(
            'original-title/original-subtitle',
            (string)$row['slug'],
            'slug must stay at the previous value when sync is toggled off in the same save',
        );

        $syncConnection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_sluggi_record_sync');
        $syncRow = $syncConnection->select(
            ['is_synced'],
            'tx_sluggi_record_sync',
            ['tablename' => 'tx_sluggitest_article', 'record_uid' => 1]
        )->fetchAssociative();
        self::assertIsArray($syncRow, 'sync state row must be persisted');
        self::assertSame(0, (int)$syncRow['is_synced'], 'sync must be off after the save');
    }

    #[Test]
    public function setRecordSyncStateCanUpdateExistingRow(): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable('tx_sluggi_record_sync');

        $connection->insert('tx_sluggi_record_sync', [
            'tablename' => 'tx_sluggitest_article',
            'record_uid' => 1,
            'is_synced' => 1,
        ]);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['tx_sluggitest_article' => [1 => ['tx_sluggi_sync' => 0]]],
            []
        );
        $dataHandler->process_datamap();

        $row = $connection->select(
            ['is_synced'],
            'tx_sluggi_record_sync',
            ['tablename' => 'tx_sluggitest_article', 'record_uid' => 1]
        )->fetchAssociative();

        self::assertIsArray($row);
        self::assertSame(0, (int)$row['is_synced'], 'Sync state should be persisted as OFF');
    }

    #[Test]
    public function syncOffIsPersistedForNewRecord(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'tx_sluggitest_article' => [
                    'NEW1' => [
                        'pid' => 0,
                        'title' => 'Brand New Article',
                        'subtitle' => 'With Custom Slug',
                        'tx_sluggi_sync' => 0,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertSame([], $dataHandler->errorLog, 'DataHandler must not produce errors');

        $newUid = (int)($dataHandler->substNEWwithIDs['NEW1'] ?? 0);
        self::assertGreaterThan(0, $newUid, 'New record must have been created');

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_sluggi_record_sync');

        $row = $connection->select(
            ['is_synced'],
            'tx_sluggi_record_sync',
            ['tablename' => 'tx_sluggitest_article', 'record_uid' => $newUid]
        )->fetchAssociative();

        self::assertIsArray($row, 'Sync state row must exist for new record with sync=OFF');
        self::assertSame(0, (int)$row['is_synced'], 'Sync OFF must be persisted for new record');
    }

    #[Test]
    public function syncStateIsNotPersistedWhenRecordUpdateIsRejected(): void
    {
        $this->setUpBackendUser(1);

        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $syncConnection = $connectionPool->getConnectionForTable('tx_sluggi_record_sync');

        $syncConnection->insert('tx_sluggi_record_sync', [
            'tablename' => 'tx_sluggitest_article',
            'record_uid' => 1,
            'is_synced' => 1,
        ]);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['tx_sluggitest_article' => [99999 => ['tx_sluggi_sync' => 0]]],
            []
        );
        $dataHandler->process_datamap();

        $row = $syncConnection->select(
            ['is_synced'],
            'tx_sluggi_record_sync',
            ['tablename' => 'tx_sluggitest_article', 'record_uid' => 99999]
        )->fetchAssociative();

        self::assertFalse($row, 'Sync state must not be persisted for non-existent record');
    }

    #[Test]
    public function pendingOverrideIsClearedAfterRejectedUpdate(): void
    {
        $this->setUpBackendUser(1);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['tx_sluggitest_article' => [99999 => ['tx_sluggi_sync' => 0]]],
            []
        );
        $dataHandler->process_datamap();

        $syncService = GeneralUtility::makeInstance(SlugSyncService::class);

        self::assertTrue(
            $syncService->getRecordSyncState('tx_sluggitest_article', 99999),
            'Pending override must be cleared after DataHandler finishes — stale state must not leak'
        );
    }

    #[Test]
    public function syncStateIsNotPersistedWhenUserLacksFieldPermission(): void
    {
        $GLOBALS['TCA']['tx_sluggitest_article']['columns']['tx_sluggi_sync']['exclude'] = true;

        $this->setUpBackendUser(2);

        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $syncConnection = $connectionPool->getConnectionForTable('tx_sluggi_record_sync');

        $syncConnection->insert('tx_sluggi_record_sync', [
            'tablename' => 'tx_sluggitest_article',
            'record_uid' => 1,
            'is_synced' => 1,
        ]);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['tx_sluggitest_article' => [1 => ['title' => 'New Title', 'tx_sluggi_sync' => 0]]],
            []
        );
        $dataHandler->process_datamap();

        $row = $syncConnection->select(
            ['is_synced'],
            'tx_sluggi_record_sync',
            ['tablename' => 'tx_sluggitest_article', 'record_uid' => 1]
        )->fetchAssociative();

        self::assertIsArray($row);
        self::assertSame(
            1,
            (int)$row['is_synced'],
            'Sync state must NOT change when user lacks field permission'
        );
    }

    #[Test]
    public function slugIsNotRegeneratedWhenSyncIsOff(): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable('tx_sluggi_record_sync');

        $connection->insert('tx_sluggi_record_sync', [
            'tablename' => 'tx_sluggitest_article',
            'record_uid' => 1,
            'is_synced' => 0,
        ]);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['tx_sluggitest_article' => [1 => ['title' => 'Changed Title']]],
            []
        );
        $dataHandler->process_datamap();

        $row = $connectionPool
            ->getConnectionForTable('tx_sluggitest_article')
            ->select(['slug'], 'tx_sluggitest_article', ['uid' => 1])
            ->fetchAssociative();

        self::assertSame(
            'original-title/original-subtitle',
            $row['slug'],
            'Slug must NOT regenerate when sync is OFF'
        );
    }
}
