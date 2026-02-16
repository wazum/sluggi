<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

/**
 * Tests cascade slug updates when last_segment_only is enabled.
 * When a page slug changes, TYPO3 automatically updates child page slugs.
 * These cascade updates must pass validation even though tx_sluggi_full_path
 * is only set for the directly edited page, not for child pages.
 */
final class ChildPageSlugUpdateTest extends FunctionalTestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_parent_child_hierarchy.csv');
        $this->setUpSite();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function updatingChildPageSlugWithFullPathAlsoUpdatesGrandchild(): void
    {
        $this->setUpBackendUser(2);

        // Child page (uid=3) has slug /custom-parent/child
        // Grandchild page (uid=4) has slug /custom-parent/child/grandchild
        // We change child to /parent/child, which should also update grandchild

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

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_parent_child_hierarchy_updated.csv');
        self::assertEmpty(
            $dataHandler->errorLog,
            'Updating child page with full path permission should also update grandchild without errors. Errors: '
            . implode(', ', $dataHandler->errorLog)
        );
    }

    #[Test]
    public function editorCanRenamePageWithChildrenWhenAutoCreateRedirectsEnabled(): void
    {
        Typo3Compatibility::writeSiteConfiguration('test', [
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
            'settings' => [
                'redirects' => [
                    'autoUpdateSlugs' => true,
                    'autoCreateRedirects' => true,
                ],
            ],
        ]);
        $this->setUpBackendUser(2);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['pages' => [3 => ['slug' => '/custom-parent/child-new']]],
            [],
        );
        $dataHandler->process_datamap();

        self::assertEmpty(
            $dataHandler->errorLog,
            'Editor renaming page with autoCreateRedirects should not produce permission errors. Errors: '
            . implode(', ', $dataHandler->errorLog),
        );

        $child = BackendUtility::getRecord('pages', 3, 'slug');
        self::assertSame('/custom-parent/child-new', $child['slug']);

        $grandchild = BackendUtility::getRecord('pages', 4, 'slug');
        self::assertSame('/custom-parent/child-new/grandchild', $grandchild['slug']);
    }

    #[Test]
    public function editorRenamingPageCreatesRedirectsWhenAutoCreateRedirectsEnabled(): void
    {
        Typo3Compatibility::writeSiteConfiguration('test', [
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
            'settings' => [
                'redirects' => [
                    'autoUpdateSlugs' => true,
                    'autoCreateRedirects' => true,
                ],
            ],
        ]);
        $this->setUpBackendUser(2);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['pages' => [3 => ['slug' => '/custom-parent/child-new']]],
            [],
        );
        $dataHandler->process_datamap();

        $child = BackendUtility::getRecord('pages', 3, 'slug');
        self::assertSame('/custom-parent/child-new', $child['slug']);

        $redirectCount = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_redirect')
            ->count('*', 'sys_redirect', ['source_path' => '/custom-parent/child']);
        self::assertGreaterThan(
            0,
            $redirectCount,
            'A redirect should have been created for the old slug /custom-parent/child',
        );
    }

    #[Test]
    public function editorWithSubTreeMountCreatesRedirectsWhenAutoCreateRedirectsEnabled(): void
    {
        Typo3Compatibility::writeSiteConfiguration('test', [
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
            'settings' => [
                'redirects' => [
                    'autoUpdateSlugs' => true,
                    'autoCreateRedirects' => true,
                ],
            ],
        ]);
        $this->setUpBackendUser(2);
        // Editor mounted on sub-tree only, not root page.
        // Redirects are stored at pid = site root page (1), which editor cannot normally access.
        $GLOBALS['BE_USER']->groupData['webmounts'] = '2';

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['pages' => [3 => ['slug' => '/custom-parent/child-new']]],
            [],
        );
        $dataHandler->process_datamap();

        $child = BackendUtility::getRecord('pages', 3, 'slug');
        self::assertSame('/custom-parent/child-new', $child['slug']);

        $redirectCount = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_redirect')
            ->count('*', 'sys_redirect', ['source_path' => '/custom-parent/child']);
        self::assertGreaterThan(
            0,
            $redirectCount,
            'A redirect should have been created even when editor has no root page in webmounts',
        );
    }
}
