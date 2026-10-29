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
    public function editorNewPageUnderExcludedPageTypeIsLockedToEffectiveParentPath(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_new_page_under_excluded_folder.csv');
        $this->setUpSite();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        $this->setUpBackendUser(2);

        // The editor may create pages below the storage folder (uid=3) but not
        // edit it, so no rootline page is editable and the locked prefix falls
        // back to the parent path. The folder is an excluded page type with an
        // empty slug, so the effective parent path is the one of "Home".
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW1' => [
                        'pid' => 3,
                        'title' => 'New Page',
                        'slug' => '/totally/different/new-page',
                        'doktype' => 1,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertEmpty($dataHandler->errorLog, implode(' | ', $dataHandler->errorLog));

        $slug = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT slug FROM pages WHERE uid = 4')
            ->fetchOne();

        self::assertSame('/home/new-page', $slug);
    }

    #[Test]
    public function editorCanFixOutOfSyncSlugWithCorrectHierarchyPrefix(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_out_of_sync.csv');
        $this->setUpSite();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        $this->setUpBackendUser(2);

        // Page 5 has slug /wrong-path/about-us but parent (uid=4) has slug /home/department/institute
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    5 => [
                        'slug' => '/home/department/institute/fixed-page',
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

        self::assertSame('/home/department/institute/fixed-page', $row['slug']);
        self::assertEmpty($dataHandler->errorLog, 'No error expected: ' . implode(', ', $dataHandler->errorLog));
    }

    #[Test]
    public function editorCanSavePageWithUnchangedOutOfSyncSlug(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_out_of_sync.csv');
        $this->setUpSite();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        $this->setUpBackendUser(2);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    5 => [
                        'title' => 'Updated About Us',
                        'slug' => '/wrong-path/about-us',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $row = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->select(['slug', 'title'], 'pages', ['uid' => 5])
            ->fetchAssociative();

        self::assertSame('Updated About Us', $row['title']);
        self::assertSame('/wrong-path/about-us', $row['slug'], 'Unchanged slug must be preserved');
        self::assertEmpty($dataHandler->errorLog, 'No error expected: ' . implode(', ', $dataHandler->errorLog));
    }

    #[Test]
    public function editorCanEditLastSegmentOfOutOfHierarchySlugPreservingParentPath(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_out_of_sync.csv');
        $this->setUpSite();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        $this->setUpBackendUser(2);

        // Page 5 has slug /wrong-path/about-us — editor changes only last segment
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    5 => [
                        'slug' => '/wrong-path/new-page',
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

        self::assertSame('/wrong-path/new-page', $row['slug'], 'Last-segment-only change should be allowed');
        self::assertEmpty($dataHandler->errorLog, 'No error expected: ' . implode(', ', $dataHandler->errorLog));
    }

    #[Test]
    public function moveInducedSlugUpdateIsNotBlockedByHierarchyPermission(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_move.csv');
        $this->setUpSite();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        $this->setUpBackendUser(2);

        // Editor may create pages below Institute Two (uid=6) but not edit it,
        // so the hierarchy prefix derived from their permissions does not
        // cover the destination — the move must still rewrite the slug
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            'pages' => [
                5 => [
                    'move' => 6,
                ],
            ],
        ]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_after_move.csv');
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

    #[Test]
    public function editorNewPageWithEmptySlugGetsGeneratedSlug(): void
    {
        $this->setUpBackendUser(2);

        // The slug element submits an empty slug for a new page that has no own
        // segment yet; the core generates it from the title. Nothing to validate
        // at this point: the slug must not degrade to the bare hierarchy prefix.
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW1' => [
                        'pid' => 4,
                        'title' => 'New Page',
                        'slug' => '',
                        'doktype' => 1,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertEmpty($dataHandler->errorLog, implode(' | ', $dataHandler->errorLog));
        self::assertSame('/home/department/institute/new-page', $this->fetchSlug(6));
    }

    #[Test]
    public function editorNewPageWithEmptySlugBelowCustomParentPathFollowsTheParent(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_custom_parent.csv');
        $this->setUpSite();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        $this->setUpBackendUser(2);

        // An administrator gave "About Us" (uid=5) the custom path /about, which
        // lies outside the editor's hierarchy prefix /home/department. A new
        // page below it gets its slug generated from the parent, not the prefix.
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW1' => [
                        'pid' => 5,
                        'title' => 'New Page',
                        'slug' => '',
                        'doktype' => 1,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertEmpty($dataHandler->errorLog, implode(' | ', $dataHandler->errorLog));
        self::assertSame('/about/new-page', $this->fetchSlug(6));
    }

    #[Test]
    public function editorNewPageWithSlugFollowingCustomParentPathIsAccepted(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_custom_parent.csv');
        $this->setUpSite();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        $this->setUpBackendUser(2);

        // The form submits the proposal built from the parent's actual slug.
        // Following the parent cannot leave the permitted hierarchy any further
        // than the parent already did, so it must not be rewritten.
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW1' => [
                        'pid' => 5,
                        'title' => 'New Page',
                        'slug' => '/about/new-page',
                        'doktype' => 1,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertEmpty($dataHandler->errorLog, implode(' | ', $dataHandler->errorLog));
        self::assertSame('/about/new-page', $this->fetchSlug(6));
    }

    #[Test]
    public function editorNewPageWithArbitrarySlugBelowCustomParentPathIsRewritten(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_custom_parent.csv');
        $this->setUpSite();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        $this->setUpBackendUser(2);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW1' => [
                        'pid' => 5,
                        'title' => 'New Page',
                        'slug' => '/totally/different/new-page',
                        'doktype' => 1,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertSame('/home/department/new-page', $this->fetchSlug(6));
    }

    private function fetchSlug(int $uid): string
    {
        return (string)$this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT slug FROM pages WHERE uid = ' . $uid)
            ->fetchOne();
    }
}
