<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandling;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

/**
 * Pins the contract between sluggi and b13/masi when both are installed.
 *
 * Contract:
 *   1. masi-managed doktypes (199 Spacer, 254 Sysfolder): masi owns them.
 *      Sluggi drops those values from exclude_doktypes at boot. The per-page
 *      checkbox is the only opt-out for those.
 *   2. Any other doktype in exclude_doktypes (custom 100, 137, ...) is still
 *      honored via SluggiAwareSlugModifier merging into masi's parent-skip
 *      loop.
 *
 * The class-level `configurationToUseInTestInstance` reproduces the user's
 * typical config — sluggi.exclude_doktypes='199,254' — so the boot filter in
 * ext_localconf.php is exercised end-to-end. Tests that need a different
 * starting config override $GLOBALS directly (the boot filter does not re-run
 * mid-test); those tests simulate the post-filter state.
 */
final class SlugHelperWithMasiTest extends FunctionalTestCase
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
                'exclude_doktypes' => '199,254',
            ],
        ],
    ];

    private function setUpSite(): void
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
        ]);
    }

    private function setUpTest(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../DataHandler/Fixtures/pages_for_copy_within_sysfolder.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
    }

    private function createSlugHelper(): SlugHelper
    {
        $fieldConfig = $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? [];

        return GeneralUtility::makeInstance(
            SlugHelper::class,
            'pages',
            'slug',
            $fieldConfig
        );
    }

    #[Test]
    public function masiKeepsSysfolderInSlugWhenSluggiHasDefaultExcludeDoktypes(): void
    {
        // End-to-end: sluggi.exclude_doktypes='199,254' is configured BEFORE
        // bootstrap (via configurationToUseInTestInstance), so the boot filter
        // runs, drops 199 and 254, and masi's "include by default" wins.
        $this->setUpTest();

        $slugHelper = $this->createSlugHelper();
        $record = ['title' => 'Page to Copy', 'uid' => 3];
        $slug = $slugHelper->generate($record, 2);

        self::assertSame('/sysfolder/page-to-copy', $slug);
    }

    #[Test]
    public function masiPerPageFlagStripsSysfolderFromSlug(): void
    {
        $this->setUpTest();

        $this->getConnectionPool()->getConnectionForTable('pages')->update(
            'pages',
            ['exclude_slug_for_subpages' => 1],
            ['uid' => 2]
        );

        $slugHelper = $this->createSlugHelper();
        $record = ['title' => 'Page to Copy', 'uid' => 3];
        $slug = $slugHelper->generate($record, 2);

        self::assertSame('/page-to-copy', $slug);
    }

    #[Test]
    public function sysfolderStaysInSlugWhenNeitherSignalExcludesIt(): void
    {
        $this->setUpTest();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] = '';

        $slugHelper = $this->createSlugHelper();
        $record = ['title' => 'Page to Copy', 'uid' => 3];
        $slug = $slugHelper->generate($record, 2);

        self::assertSame('/sysfolder/page-to-copy', $slug);
    }

    #[Test]
    public function customDoktypeInExcludeDoktypesIsHonoredWhenMasiIsLoaded(): void
    {
        $this->setUpTest();

        // Re-purpose page 2 from doktype 254 to a custom doktype 137.
        $this->getConnectionPool()->getConnectionForTable('pages')->update(
            'pages',
            ['doktype' => 137],
            ['uid' => 2]
        );

        // Simulate the post-boot-filter effective state: '100,137' would
        // survive the filter unchanged (137 is not masi-managed).
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] = '137';

        $slugHelper = $this->createSlugHelper();
        $record = ['title' => 'Page to Copy', 'uid' => 3];
        $slug = $slugHelper->generate($record, 2);

        self::assertSame('/page-to-copy', $slug);
    }

    #[Test]
    public function customDoktypeNotInExcludeDoktypesStaysInSlug(): void
    {
        $this->setUpTest();

        $this->getConnectionPool()->getConnectionForTable('pages')->update(
            'pages',
            ['doktype' => 137],
            ['uid' => 2]
        );

        $slugHelper = $this->createSlugHelper();
        $record = ['title' => 'Page to Copy', 'uid' => 3];
        $slug = $slugHelper->generate($record, 2);

        self::assertSame('/sysfolder/page-to-copy', $slug);
    }
}
