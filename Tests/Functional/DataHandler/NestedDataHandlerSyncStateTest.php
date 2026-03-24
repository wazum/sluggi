<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class NestedDataHandlerSyncStateTest extends FunctionalTestCase
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
    public function syncStateSurvivesNestedDataHandlerCall(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['test_nested_datahandler'] =
                NestedDataHandlerHook::class;

        try {
            NestedDataHandlerHook::$targetTable = 'tx_sluggitest_article';
            NestedDataHandlerHook::$targetUid = 2;
            NestedDataHandlerHook::$triggered = false;

            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start(
                ['tx_sluggitest_article' => [1 => ['title' => 'Updated Title', 'tx_sluggi_sync' => 0]]],
                []
            );
            $dataHandler->process_datamap();

            self::assertTrue(NestedDataHandlerHook::$triggered, 'Nested DataHandler hook must have fired');

            $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('tx_sluggi_record_sync');

            $row = $connection->select(
                ['is_synced'],
                'tx_sluggi_record_sync',
                ['tablename' => 'tx_sluggitest_article', 'record_uid' => 1]
            )->fetchAssociative();

            self::assertIsArray($row, 'Sync state row must exist for record 1');
            self::assertSame(
                0,
                (int)$row['is_synced'],
                'Sync state for record 1 must survive the nested DataHandler — shared: false prevents premature cleanup'
            );
        } finally {
            unset(
                $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['test_nested_datahandler']
            );
        }
    }
}

/**
 * Test hook that fires a nested DataHandler during the outer DataHandler's processing.
 * This simulates the scenario where a hook (e.g. ValidateReservedSlugPath) spawns
 * a nested DataHandler that calls afterAllOperations before the outer finishes.
 */
final class NestedDataHandlerHook
{
    public static string $targetTable = '';
    public static int $targetUid = 0;
    public static bool $triggered = false;

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_postProcessFieldArray(
        string $status,
        string $table,
        string|int $id,
        array &$fieldArray,
        DataHandler $dataHandler,
    ): void {
        if (self::$triggered || $table !== self::$targetTable || (int)$id !== 1) {
            return;
        }

        self::$triggered = true;

        $nestedDataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $nestedDataHandler->start(
            [self::$targetTable => [self::$targetUid => ['title' => 'Nested Update']]],
            []
        );
        $nestedDataHandler->process_datamap();
    }
}
