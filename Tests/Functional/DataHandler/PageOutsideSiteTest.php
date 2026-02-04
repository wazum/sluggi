<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class PageOutsideSiteTest extends FunctionalTestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_outside_site.csv');

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
                    'autoCreateRedirects' => false,
                ],
            ],
        ]);

        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function slugIsRegeneratedForPageOutsideSiteWithoutException(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    101 => [
                        'title' => 'Updated Customer',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertEmpty($dataHandler->errorLog, 'DataHandler should not have errors');
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_outside_site_after_title_change.csv');
    }

    #[Test]
    public function sysFolderOutsideSiteDoesNotThrowOnTitleChange(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    100 => [
                        'title' => 'Renamed Folder',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertEmpty($dataHandler->errorLog, 'DataHandler should not have errors for SysFolder title change');
    }

    #[Test]
    public function copyPageOutsideSiteDoesNotThrow(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [],
            [
                'pages' => [
                    101 => [
                        'copy' => 100,
                    ],
                ],
            ]
        );
        $dataHandler->process_cmdmap();

        self::assertEmpty($dataHandler->errorLog, 'Copying a page outside a site should not produce errors');
    }

    #[Test]
    public function movePageOutsideSiteDoesNotThrow(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [],
            [
                'pages' => [
                    2 => [
                        'move' => 100,
                    ],
                ],
            ]
        );
        $dataHandler->process_cmdmap();

        self::assertEmpty($dataHandler->errorLog, 'Moving a page outside a site should not produce errors');
    }
}
