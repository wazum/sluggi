<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

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
        Typo3Compatibility::writeSiteConfiguration('test', $configuration);
    }

    private function setUpTest(string $fixture, int $backendUserId): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/' . $fixture);
        $this->setUpSite();
        $this->setUpBackendUser($backendUserId);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function userWithFullPathPermissionCanChangeParentSegment(): void
    {
        $this->setUpTest('pages_full_path.csv', 3);

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
        $this->setUpTest('pages_full_path.csv', 2);

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
        $this->setUpTest('pages_full_path.csv', 3);

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

    #[Test]
    public function fullPathEditLocksSlugWhenSlugChanges(): void
    {
        $this->setUpTest('pages_full_path.csv', 3);

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

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_full_path_changed_locked.csv');
        self::assertEmpty($dataHandler->errorLog, 'No errors expected');
    }

    #[Test]
    public function fullPathEditDoesNotLockSlugWhenSlugUnchanged(): void
    {
        $this->setUpTest('pages_full_path.csv', 3);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    3 => [
                        'slug' => '/parent/child',
                        'tx_sluggi_full_path' => '1',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_full_path_unchanged_not_locked.csv');
        self::assertEmpty($dataHandler->errorLog, 'No errors expected');
    }

    #[Test]
    public function fullPathEditDoesNotLockSlugWhenRevertedToHierarchy(): void
    {
        $this->setUpTest('pages_full_path_custom.csv', 3);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    3 => [
                        'slug' => '/parent/child',
                        'tx_sluggi_full_path' => '1',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_full_path_reverted_not_locked.csv');
        self::assertEmpty($dataHandler->errorLog, 'No errors expected');
    }
}
