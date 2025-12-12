<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class LastSegmentValidationTest extends FunctionalTestCase
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
                'synchronize' => '0',
                'last_segment_only' => '1',
            ],
        ],
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
        ];
        GeneralUtility::makeInstance(SiteWriter::class)->write('test', $configuration);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_last_segment.csv');
        $this->setUpSite();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function nonAdminCanChangeLastSegmentOnly(): void
    {
        $this->setUpBackendUser(2);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    3 => [
                        'slug' => '/parent/new-child',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_last_segment_valid_change.csv');
    }

    #[Test]
    public function nonAdminCannotChangeParentSegment(): void
    {
        $this->setUpBackendUser(2);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    3 => [
                        'slug' => '/different-parent/child',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_last_segment_blocked.csv');
        self::assertNotEmpty($dataHandler->errorLog, 'Expected an error to be logged');
    }

    #[Test]
    public function adminCanChangeAnySegment(): void
    {
        $this->setUpBackendUser(1);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    3 => [
                        'slug' => '/completely/different/path',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $row = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->select(['slug'], 'pages', ['uid' => 3])
            ->fetchAssociative();

        self::assertSame('/completely/different/path', $row['slug']);
    }
}
