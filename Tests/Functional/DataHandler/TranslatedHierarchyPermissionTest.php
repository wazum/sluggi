<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class TranslatedHierarchyPermissionTest extends FunctionalTestCase
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
                'last_segment_only' => '0',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_translated.csv');
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
        ]);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        $this->setUpBackendUser(2);
    }

    #[Test]
    public function editorCanAddSegmentBelowEditablePageOfTranslation(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    15 => [
                        'slug' => '/startseite/abteilung/institut/extra/ueber-uns',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_translated_segment_added.csv');
    }

    #[Test]
    public function editorCannotChangeLockedSegmentOfTranslation(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    15 => [
                        'slug' => '/startseite/andere-abteilung/institut/ueber-uns',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_translated.csv');
        self::assertNotEmpty($dataHandler->errorLog);
    }

    #[Test]
    public function editorTitleChangeOfTranslationUpdatesSlug(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    15 => [
                        'title' => 'Ueber sie',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_translated_synced.csv');
    }
}
