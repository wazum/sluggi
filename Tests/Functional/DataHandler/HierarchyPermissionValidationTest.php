<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class HierarchyPermissionValidationTest extends FunctionalTestCase
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
                'last_segment_only' => '0',
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_permission.csv');
        $this->setUpSite();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function editorCanChangeLastSegmentOfPageTheyCanEdit(): void
    {
        $this->setUpBackendUser(2);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    5 => [
                        'slug' => '/home/department/institute/about-page',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_valid_change.csv');
    }

    #[Test]
    public function editorCannotChangeLockedSegmentAboveTheirPermission(): void
    {
        $this->setUpBackendUser(2);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    5 => [
                        'slug' => '/home/other-department/institute/about-us',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_blocked.csv');
        self::assertNotEmpty($dataHandler->errorLog, 'Expected an error to be logged');
    }

    #[Test]
    public function editorCanChangeSegmentOfPageTheyHavePermissionOn(): void
    {
        $this->setUpBackendUser(2);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    4 => [
                        'slug' => '/home/department/our-institute',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_change_editable_segment.csv');
    }

    #[Test]
    public function adminCanChangeAnySegmentRegardlessOfPermissions(): void
    {
        $this->setUpBackendUser(1);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    5 => [
                        'slug' => '/completely/different/path/about-us',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $row = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->select(['slug'], 'pages', ['uid' => 5])
            ->fetchAssociative();

        self::assertSame('/completely/different/path/about-us', $row['slug']);
    }

    #[Test]
    public function editorNewPageCannotBypassHierarchyPermission(): void
    {
        $this->setUpBackendUser(2);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW1' => [
                        'pid' => 4,
                        'title' => 'New Page',
                        'slug' => '/totally/different/new-page',
                        'doktype' => 1,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_new_page_blocked.csv');
    }

    #[Test]
    public function editorCannotRemoveLockedSegments(): void
    {
        $this->setUpBackendUser(2);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    5 => [
                        'slug' => '/home/about-us',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_blocked.csv');
        self::assertNotEmpty($dataHandler->errorLog, 'Expected an error to be logged');
    }
}
