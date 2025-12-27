<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class ClearSlugForExcludedDoktypesTest extends FunctionalTestCase
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
                'exclude_doktypes' => '199,254,255',
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_excluded_doktypes.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function clearsSlugWhenSavingPageWithExcludedDoktype(): void
    {
        // Page 2 is a sysfolder (254) with existing slug - slug should be cleared on save
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    2 => [
                        'title' => 'Updated Sysfolder',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_sysfolder_save.csv');
    }

    #[Test]
    public function keepsSlugWhenSavingPageWithNonExcludedDoktype(): void
    {
        // Page 3 is a standard page (1) - slug should be kept
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    3 => [
                        'title' => 'Updated Standard Page',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_standard_save.csv');
    }

    #[Test]
    public function clearsSlugWhenDoktypeChangesToExcluded(): void
    {
        // Page 3 is standard (1) - change to sysfolder (254), slug should be cleared
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    3 => [
                        'doktype' => 254,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_doktype_to_sysfolder.csv');
    }

    #[Test]
    public function clearsSlugWhenCreatingNewPageWithExcludedDoktype(): void
    {
        // Create new sysfolder - slug should be empty (next UID is 4)
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW1' => [
                        'pid' => 1,
                        'title' => 'New Sysfolder',
                        'doktype' => 254,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_new_sysfolder.csv');
    }

    #[Test]
    public function generatesSlugWhenCreatingNewPageWithNonExcludedDoktype(): void
    {
        // Create new standard page - slug should be generated (next UID is 4)
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW1' => [
                        'pid' => 1,
                        'title' => 'New Standard Page',
                        'doktype' => 1,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_new_standard.csv');
    }
}
