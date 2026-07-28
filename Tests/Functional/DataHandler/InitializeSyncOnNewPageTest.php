<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Tests that tx_sluggi_sync is set to 1 for new pages created via DataHandler
 * (e.g. drag and drop in page tree) when synchronize and synchronize_default are enabled.
 */
final class InitializeSyncOnNewPageTest extends FunctionalTestCase
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
                'synchronize_default' => '1',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function newPageCreatedViaDataHandlerHasSyncEnabled(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_new_page.csv');
        $this->setUpBackendUser(1);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW123' => [
                        'pid' => 1,
                        'title' => 'New Page via DataHandler',
                        'doktype' => 1,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_new_with_sync.csv');
    }

    #[Test]
    public function newPageHasSyncEnabledForEditorWithoutSyncFieldPermission(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_new_page_restricted_editor.csv');
        $this->setUpBackendUser(2);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW123' => [
                        'pid' => 2,
                        'title' => 'New Page via DataHandler',
                        'doktype' => 1,
                        'sys_language_uid' => 0,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertSame([], $dataHandler->errorLog);

        $newPageId = (int)$dataHandler->substNEWwithIDs['NEW123'];
        $syncState = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT tx_sluggi_sync FROM pages WHERE uid = ?', [$newPageId])
            ->fetchOne();

        self::assertSame(1, (int)$syncState);
    }

    #[Test]
    public function newPageHasSyncEnabledWhenSubmittedValueIsStrippedByAccessControl(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_new_page_restricted_editor.csv');
        $this->setUpBackendUser(2);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW123' => [
                        'pid' => 2,
                        'title' => 'New Page via FormEngine',
                        'doktype' => 1,
                        'sys_language_uid' => 0,
                        'tx_sluggi_sync' => 1,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertSame([], $dataHandler->errorLog);

        $newPageId = (int)$dataHandler->substNEWwithIDs['NEW123'];
        $syncState = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT tx_sluggi_sync FROM pages WHERE uid = ?', [$newPageId])
            ->fetchOne();

        self::assertSame(1, (int)$syncState);
    }

    #[Test]
    public function newPageUsesPageTsConfigDefaultWithoutSyncFieldPermission(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['synchronize_default'] = '0';
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_tcadefaults_sync.csv');
        $this->setUpBackendUser(2);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW123' => [
                        'pid' => 2,
                        'title' => 'New Page',
                        'doktype' => 1,
                        'sys_language_uid' => 0,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertSame([], $dataHandler->errorLog);

        $newPageId = (int)$dataHandler->substNEWwithIDs['NEW123'];
        $syncState = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT tx_sluggi_sync FROM pages WHERE uid = ?', [$newPageId])
            ->fetchOne();

        self::assertSame(1, (int)$syncState);
    }

    #[Test]
    public function pageTsConfigDefaultOverridesSynchronizeDefaultSetting(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_tcadefaults_sync.csv');
        $this->setUpBackendUser(3);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW123' => [
                        'pid' => 3,
                        'title' => 'New Page',
                        'doktype' => 1,
                        'sys_language_uid' => 0,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertSame([], $dataHandler->errorLog);

        $newPageId = (int)$dataHandler->substNEWwithIDs['NEW123'];
        $syncState = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT tx_sluggi_sync FROM pages WHERE uid = ?', [$newPageId])
            ->fetchOne();

        self::assertSame(0, (int)$syncState);
    }

    #[Test]
    public function excludedPageTypeKeepsSyncDisabledDespitePageTsConfigDefault(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] = '199,254';
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_tcadefaults_sync.csv');
        $this->setUpBackendUser(1);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW123' => [
                        'pid' => 2,
                        'title' => 'New Folder',
                        'doktype' => 254,
                        'sys_language_uid' => 0,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertSame([], $dataHandler->errorLog);

        $newPageId = (int)$dataHandler->substNEWwithIDs['NEW123'];
        $syncState = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT tx_sluggi_sync FROM pages WHERE uid = ?', [$newPageId])
            ->fetchOne();

        self::assertSame(0, (int)$syncState);
    }

    #[Test]
    public function newPageKeepsSyncDisabledWhenEditorExplicitlyTurnsItOff(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_new_page.csv');
        $this->setUpBackendUser(1);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW123' => [
                        'pid' => 1,
                        'title' => 'New Page via DataHandler',
                        'doktype' => 1,
                        'tx_sluggi_sync' => 0,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertSame([], $dataHandler->errorLog);

        $newPageId = (int)$dataHandler->substNEWwithIDs['NEW123'];
        $syncState = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT tx_sluggi_sync FROM pages WHERE uid = ?', [$newPageId])
            ->fetchOne();

        self::assertSame(0, (int)$syncState);
    }
}
