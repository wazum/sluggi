<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class HandleRecordUpdateTest extends FunctionalTestCase
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
    public function slugIsRegeneratedWhenTitleChanges(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'tx_sluggitest_article' => [
                    1 => [
                        'title' => 'Updated Title',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/article_after_title_change.csv');
    }

    #[Test]
    public function slugIsRegeneratedWhenSubtitleChanges(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'tx_sluggitest_article' => [
                    1 => [
                        'subtitle' => 'New Subtitle',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/article_after_subtitle_change.csv');
    }

    #[Test]
    public function slugGetsUniqueSuffixWhenConflictExistsInTable(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'tx_sluggitest_article' => [
                    2 => [
                        'title' => 'Original Title',
                        'subtitle' => 'Original Subtitle',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $row = $this->getConnectionPool()
            ->getConnectionForTable('tx_sluggitest_article')
            ->select(['slug'], 'tx_sluggitest_article', ['uid' => 2])
            ->fetchAssociative();

        self::assertNotSame(
            'original-title/original-subtitle',
            $row['slug'],
            'Slug must be unique across the table (eval=unique) and get a suffix'
        );
    }

    #[Test]
    public function slugIsRegeneratedWhenBothFieldsChange(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'tx_sluggitest_article' => [
                    1 => [
                        'title' => 'New Title',
                        'subtitle' => 'New Subtitle',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/article_after_both_fields_change.csv');
    }
}
