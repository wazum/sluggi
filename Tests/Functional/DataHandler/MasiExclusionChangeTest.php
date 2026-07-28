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

final class MasiExclusionChangeTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
        'b13/masi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'sluggi' => [
                'synchronize' => '1',
                'lock' => '1',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_masi_be_admin.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    private function setUpSite(bool $withGerman = false): void
    {
        $languages = [[
            'languageId' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
            'base' => '/',
        ]];
        if ($withGerman) {
            $languages[] = [
                'languageId' => 1,
                'title' => 'German',
                'locale' => 'de_DE.UTF-8',
                'base' => '/de/',
            ];
        }
        Typo3Compatibility::writeSiteConfiguration('test', [
            'rootPageId' => 1,
            'base' => '/',
            'languages' => $languages,
            'settings' => ['redirects' => ['autoUpdateSlugs' => true, 'autoCreateRedirects' => false]],
        ]);
    }

    #[Test]
    public function childSlugRegeneratedWhenParentExclusionToggledOn(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_masi_exclusion_off.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [2 => ['exclude_slug_for_subpages' => 1]]], []);
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_masi_after_toggle_on.csv');
    }

    #[Test]
    public function exclusionCannotBeSetOnATranslationAlone(): void
    {
        $this->setUpSite(withGerman: true);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_masi_exclusion_off_translated.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [10 => ['exclude_slug_for_subpages' => 1]]], []);
        $dataHandler->process_datamap();

        $flag = $this->getConnectionPool()->getConnectionForTable('pages')
            ->executeQuery('SELECT exclude_slug_for_subpages FROM pages WHERE uid = 10')->fetchOne();

        self::assertSame(0, (int)$flag, 'The flag must stay coupled to the default language');
    }

    #[Test]
    public function defaultLanguageToggleAlsoRegeneratesTranslatedChildren(): void
    {
        $this->setUpSite(withGerman: true);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_masi_exclusion_off_translated.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [2 => ['exclude_slug_for_subpages' => 1]]], []);
        $dataHandler->process_datamap();

        $slugs = $this->getConnectionPool()->getConnectionForTable('pages')
            ->executeQuery('SELECT uid, slug FROM pages WHERE uid IN (3, 11) ORDER BY uid')
            ->fetchAllKeyValue();

        self::assertSame('/child-page', $slugs[3], 'Default language child');
        self::assertSame('/kind-seite', $slugs[11], 'Translated child');
    }

    #[Test]
    public function toggleReportsHowManyPathsItRegenerated(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_masi_exclusion_off.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [2 => ['exclude_slug_for_subpages' => 1]]], []);
        $dataHandler->process_datamap();

        $report = $this->get(SlugChangeReportStore::class)->getReport();

        self::assertNotNull($report, 'The toggle rewrote a path but reported nothing');
        self::assertSame(1, $report['pagesUpdated']);
        self::assertSame(2, $report['cascadeRoot']['pageId']);
        self::assertSame('Section', $report['cascadeRoot']['title']);
    }

    #[Test]
    public function childSlugRegeneratedWhenParentExclusionToggledOff(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_masi_exclusion_on.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [2 => ['exclude_slug_for_subpages' => 0]]], []);
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_masi_after_toggle_off.csv');
    }

    #[Test]
    public function lockedChildSlugNotUpdatedWhenParentExclusionChanges(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_masi_exclusion_off_locked_child.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [2 => ['exclude_slug_for_subpages' => 1]]], []);
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_masi_after_toggle_on_locked_child.csv');
    }

    #[Test]
    public function noCascadeWhenExclusionFieldSubmittedWithSameValue(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_masi_exclusion_off_stale_child.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [2 => ['exclude_slug_for_subpages' => 0]]], []);
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_masi_exclusion_off_stale_child.csv');
    }

    #[Test]
    public function noCascadeWhenExclusionFieldSubmittedWithSameTrueValue(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_masi_exclusion_on_stale_child.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [2 => ['exclude_slug_for_subpages' => 1]]], []);
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_masi_exclusion_on_stale_child.csv');
    }

    #[Test]
    public function grandchildSlugRegeneratedRecursivelyWhenParentExclusionToggledOn(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_masi_exclusion_off_with_grandchild.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [2 => ['exclude_slug_for_subpages' => 1]]], []);
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_masi_after_toggle_on_with_grandchild.csv');
    }

    #[Test]
    public function syncDisabledChildSlugRegeneratedWhenParentExclusionToggledOn(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_masi_exclusion_off_sync_disabled_child.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [2 => ['exclude_slug_for_subpages' => 1]]], []);
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_masi_after_toggle_on_sync_disabled_child.csv');
    }
}
