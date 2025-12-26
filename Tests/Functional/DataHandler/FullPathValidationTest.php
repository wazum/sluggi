<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class FullPathValidationTest extends FunctionalTestCase
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
                'allow_full_path_editing' => '1',
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_full_path.csv');
        $this->setUpSite();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function userWithFullPathPermissionCanChangeParentSegment(): void
    {
        $this->setUpBackendUser(3);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    3 => [
                        'slug' => '/different-parent/child',
                        'tx_sluggi_full_path' => '1',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_full_path_changed.csv');
        self::assertEmpty($dataHandler->errorLog, 'No errors expected');
    }

    #[Test]
    public function userWithoutFullPathPermissionCannotChangeParentSegment(): void
    {
        $this->setUpBackendUser(2);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    3 => [
                        'slug' => '/different-parent/child',
                        'tx_sluggi_full_path' => '1',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_full_path_unchanged.csv');
        self::assertNotEmpty($dataHandler->errorLog, 'Expected an error to be logged');
    }

    #[Test]
    public function fullPathFlagIsNotStoredInDatabase(): void
    {
        $this->setUpBackendUser(3);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    3 => [
                        'slug' => '/different-parent/child',
                        'tx_sluggi_full_path' => '1',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_full_path_changed.csv');
    }
}
