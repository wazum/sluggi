<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class HandlePageUpdateTest extends FunctionalTestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_with_sync.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function slugIsRegeneratedWhenTitleChangesAndSyncEnabled(): void
    {
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

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_title_change.csv');
    }

    #[Test]
    public function slugIsNotRegeneratedWhenTitleIsClearedAndSyncEnabled(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    2 => [
                        'title' => '   ',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_title_cleared.csv');
    }

    #[Test]
    public function translatedSlugUsesTranslatedParentPrefixWhenTitleChanges(): void
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
                [
                    'languageId' => 1,
                    'title' => 'German',
                    'locale' => 'de_DE.UTF-8',
                    'base' => '/de/',
                ],
            ],
            'settings' => [
                'redirects' => [
                    'autoUpdateSlugs' => true,
                    'autoCreateRedirects' => false,
                ],
            ],
        ]);

        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_sync_translated.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    5 => [
                        'title' => 'Neue Kind Seite',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_sync_translated_title_change.csv');
    }

    #[Test]
    public function childSlugIsUpdatedWhenParentTitleChanges(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_child.csv');

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

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_parent_title_change.csv');
    }
}
