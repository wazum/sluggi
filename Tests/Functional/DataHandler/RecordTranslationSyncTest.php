<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class RecordTranslationSyncTest extends FunctionalTestCase
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
                'synchronize_tables' => 'tx_sluggitest_translated',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/translated_records_sync_off.csv');
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function translationKeepsSlugWhenSyncIsOffForDefaultLanguageRecord(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'tx_sluggitest_translated' => [
                    2 => [
                        'title' => 'Neuer Titel',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/translated_records_sync_off_after_title_change.csv');
    }
}
