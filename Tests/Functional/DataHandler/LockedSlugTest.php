<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class LockedSlugTest extends FunctionalTestCase
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
                'lock' => '1',
                'lock_descendants' => '1',
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
            'settings' => [
                'redirects' => [
                    'autoUpdateSlugs' => true,
                    'autoCreateRedirects' => false,
                ],
            ],
        ];
        Typo3Compatibility::writeSiteConfiguration('test', $configuration);
    }

    private function setUpTest(string $fixture): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/' . $fixture);
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function lockedSlugCannotBeChangedDirectly(): void
    {
        $this->setUpTest('pages_locked.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    2 => [
                        'slug' => '/new-slug',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_locked_after_direct_edit.csv');
    }

    #[Test]
    public function lockedSlugNotRegeneratedWhenTitleChanges(): void
    {
        $this->setUpTest('pages_locked_with_sync.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    2 => [
                        'title' => 'Updated Title',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_locked_after_title_change.csv');
    }

    #[Test]
    public function lockedSlugNotUpdatedOnMove(): void
    {
        $this->setUpTest('pages_locked_for_move.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            'pages' => [
                3 => [
                    'move' => 2,
                ],
            ],
        ]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_locked_after_move.csv');
    }

    #[Test]
    public function lockStateClearedOnCopy(): void
    {
        $this->setUpTest('pages_locked_for_copy.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            'pages' => [
                3 => [
                    'copy' => 2,
                ],
            ],
        ]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_locked_after_copy.csv');
    }

    #[Test]
    public function lockedChildSlugNotUpdatedWhenParentChanges(): void
    {
        $this->setUpTest('pages_locked_child.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    2 => [
                        'title' => 'Updated Parent',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_locked_child_after_parent_change.csv');
    }
}
