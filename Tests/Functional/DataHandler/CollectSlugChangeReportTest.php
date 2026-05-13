<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;
use Wazum\Sluggi\Service\SlugChangeReportStore;

final class CollectSlugChangeReportTest extends FunctionalTestCase
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

    #[Test]
    public function singlePageRenameIncrementsPagesUpdatedAndAppendsEntry(): void
    {
        $this->saveFields(2, ['slug' => '/lonely-renamed']);

        $report = $this->getStore()->getReport();
        self::assertNotNull($report, 'Report should be populated after slug rename.');
        self::assertSame(1, $report['pagesUpdated']);
        self::assertCount(1, $report['entries']);
        self::assertSame(2, $report['entries'][2]['pageId']);
    }

    #[Test]
    public function submittedSlugEqualToCurrentDoesNotIncrementOrCreateEntry(): void
    {
        $this->saveFields(6, ['slug' => '/unchanged']);

        $report = $this->getStore()->getReport();
        self::assertNull($report, 'No-op slug submit must not write a report.');
    }

    #[Test]
    public function cascadeFromParentToVisibleChildIncrementsPagesUpdatedTwice(): void
    {
        $this->saveFields(3, ['slug' => '/parent-renamed']);

        $report = $this->getStore()->getReport();
        self::assertNotNull($report);
        self::assertSame(2, $report['pagesUpdated'], 'Parent + child both count.');
        self::assertCount(1, $report['entries'], 'Only the directly-edited parent gets an entries[] row.');
        self::assertArrayHasKey(3, $report['entries']);
        self::assertArrayNotHasKey(4, $report['entries']);
    }

    #[Test]
    public function hiddenPageRenameStillCountsPagesUpdated(): void
    {
        $this->saveFields(5, ['slug' => '/draft-renamed']);

        $report = $this->getStore()->getReport();
        self::assertNotNull($report);
        self::assertSame(1, $report['pagesUpdated']);
        self::assertSame(0, $report['redirectsCreated']);
    }

    #[Test]
    public function multiEditAccumulatesAcrossDataHandlerRuns(): void
    {
        $this->saveFields(2, ['slug' => '/lonely-a']);
        $this->saveFields(6, ['slug' => '/unchanged-renamed']);

        $report = $this->getStore()->getReport();
        self::assertNotNull($report);
        self::assertSame(2, $report['pagesUpdated']);
        self::assertCount(2, $report['entries']);
    }

    #[Test]
    public function autoSyncTitleChangeMarksPageDirectlyEdited(): void
    {
        // Page 7 has tx_sluggi_sync=1. Only the title is in the datamap;
        // HandlePageUpdate fills in the regenerated slug during post-process.
        // The directly-edited mark must still land so the toast includes the
        // page in entries[] (title + UID + revert correlations).
        $this->saveFields(7, ['title' => 'Synced Title Renamed']);

        $report = $this->getStore()->getReport();
        self::assertNotNull($report, 'Auto-sync slug regen must produce a report.');
        self::assertSame(1, $report['pagesUpdated']);
        self::assertArrayHasKey(7, $report['entries']);
        self::assertSame('Synced Title Renamed', $report['entries'][7]['title']);
        self::assertNotEmpty($report['entries'][7]['correlations']['correlationIdSlugUpdate']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_slug_change_report.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    private function setUpSite(): void
    {
        Typo3Compatibility::writeSiteConfiguration('test', [
            'rootPageId' => 1,
            'base' => '/',
            'languages' => [[
                'languageId' => 0,
                'title' => 'English',
                'locale' => 'en_US.UTF-8',
                'base' => '/',
            ]],
            'settings' => [
                'redirects' => [
                    'autoUpdateSlugs' => true,
                    'autoCreateRedirects' => true,
                ],
            ],
        ]);
    }

    private function saveFields(int $pageId, array $fields): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [$pageId => $fields]], []);
        $dataHandler->process_datamap();
    }

    private function getStore(): SlugChangeReportStore
    {
        return GeneralUtility::makeInstance(SlugChangeReportStore::class);
    }
}
