<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandling;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class SlugHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
        'workspaces',
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
                [
                    'languageId' => 1,
                    'title' => 'German',
                    'locale' => 'de_DE.UTF-8',
                    'base' => '/de/',
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

    private function setUpThreeSites(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_in_three_sites.csv');
        foreach (['main' => 1, 'second' => 10, 'third' => 20] as $identifier => $rootPageId) {
            Typo3Compatibility::writeSiteConfiguration($identifier, [
                'rootPageId' => $rootPageId,
                'base' => 'https://' . $identifier . '.example/',
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
        }
        $this->setUpBackendUser(1);
    }

    /**
     * @return list<string>
     */
    private function cachedRootlineIdentifiers(): array
    {
        return GeneralUtility::makeInstance(CacheManager::class)
            ->getCache('runtime')
            ->getBackend()
            ->findIdentifiersByTag(RootlineUtility::RUNTIME_CACHE_TAG);
    }

    private function createSlugHelper(int $workspaceId = 0): SlugHelper
    {
        $fieldConfig = $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? [];

        return GeneralUtility::makeInstance(
            SlugHelper::class,
            'pages',
            'slug',
            $fieldConfig,
            $workspaceId
        );
    }

    #[Test]
    public function constructorTriggersNoDeprecations(): void
    {
        $this->setUpTest();

        $deprecations = [];
        set_error_handler(static function (int $errorNumber, string $errorMessage) use (&$deprecations): bool {
            $deprecations[] = $errorMessage;

            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->createSlugHelper();
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
    }

    #[Test]
    public function generateExcludesSysfolderFromSlugWithDefaultConfig(): void
    {
        $this->setUpTest();

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] = '199,254';

        $slugHelper = $this->createSlugHelper();
        // Page 3 is inside sysfolder (page 2, doktype=254), pid=2
        $record = ['title' => 'Page to Copy', 'uid' => 3];
        $slug = $slugHelper->generate($record, 2);

        self::assertSame('/page-to-copy', $slug);
    }

    #[Test]
    public function generateUsesWorkspaceSlugOfLocalizedParentPage(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Workspace/Fixtures/pages_for_workspace_translation.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['BE_USER']->workspace = 1;

        $fieldConfig = $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? [];
        $slugHelper = GeneralUtility::makeInstance(
            SlugHelper::class,
            'pages',
            'slug',
            $fieldConfig,
            1
        );
        // Parent page 2 has a German translation (/eltern) with a workspace version (/eltern-ws)
        $record = ['uid' => 4, 'title' => 'Kind', 'sys_language_uid' => 1];
        $slug = $slugHelper->generate($record, 2);

        self::assertSame('/eltern-ws/kind', $slug);
    }

    #[Test]
    public function generateReplacesSlashInTitleWithFallbackCharacter(): void
    {
        $this->setUpTest();

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] = '199,254';

        $slugHelper = $this->createSlugHelper();
        $record = ['title' => 'x / x', 'uid' => 3];
        $slug = $slugHelper->generate($record, 2);

        self::assertSame('/x-x', $slug);
    }

    #[Test]
    public function generateIncludesSysfolderInSlugWhenNotExcluded(): void
    {
        $this->setUpTest();

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] = '199';

        $slugHelper = $this->createSlugHelper();
        // Page 3 is inside sysfolder (page 2, doktype=254), pid=2
        $record = ['title' => 'Page to Copy', 'uid' => 3];
        $slug = $slugHelper->generate($record, 2);

        self::assertSame('/sysfolder/page-to-copy', $slug);
    }

    #[Test]
    public function isUniqueInSiteKeepsRootlineCachesWarmWhenSlugExistsInAnotherSite(): void
    {
        $this->setUpThreeSites();
        // Page 4 ("/contact") is not involved in the check below
        GeneralUtility::makeInstance(RootlineUtility::class, 4)->get();
        $cachedBefore = $this->cachedRootlineIdentifiers();
        self::assertNotSame([], $cachedBefore);

        // "/about/team" exists in the main and third site, not in the second one
        $state = RecordStateFactory::forName('pages')->fromArray(['uid' => 11, 'pid' => 10, 'sys_language_uid' => 0]);
        self::assertTrue($this->createSlugHelper()->isUniqueInSite('/about/team', $state));

        self::assertSame([], array_diff($cachedBefore, $this->cachedRootlineIdentifiers()));
    }

    #[Test]
    public function isUniqueInSiteRejectsSlugUsedByAnotherPageOfTheSameSite(): void
    {
        $this->setUpThreeSites();

        // Page 4 ("/contact") wants "/about", which page 2 of the same site already has
        $state = RecordStateFactory::forName('pages')->fromArray(['uid' => 4, 'pid' => 1, 'sys_language_uid' => 0]);

        self::assertFalse($this->createSlugHelper()->isUniqueInSite('/about', $state));
    }

    #[Test]
    public function isUniqueInSiteIgnoresTheSlugOfTheRecordItself(): void
    {
        $this->setUpThreeSites();

        // Page 4 already has "/contact" and nobody else in the main site does
        $state = RecordStateFactory::forName('pages')->fromArray(['uid' => 4, 'pid' => 1, 'sys_language_uid' => 0]);

        self::assertTrue($this->createSlugHelper()->isUniqueInSite('/contact', $state));
    }

    #[Test]
    public function isUniqueInSiteIgnoresSlugsOfOtherLanguages(): void
    {
        $this->setUpThreeSites();

        // Page 4 (English) wants "/ueber-uns", which only the German translation of page 2 has
        $state = RecordStateFactory::forName('pages')->fromArray(['uid' => 4, 'pid' => 1, 'sys_language_uid' => 0]);

        self::assertTrue($this->createSlugHelper()->isUniqueInSite('/ueber-uns', $state));
    }

    #[Test]
    public function isUniqueInSiteResolvesTheSiteOfANewPageFromItsParent(): void
    {
        $this->setUpThreeSites();

        // A new page below the second site's root wants "/about", which page 11 of that site has
        $state = RecordStateFactory::forName('pages')->fromArray(['pid' => 10, 'sys_language_uid' => 0], 10, 'NEW1');

        self::assertFalse($this->createSlugHelper()->isUniqueInSite('/about', $state));
    }

    #[Test]
    public function isUniqueInSiteSeesAPageMovedIntoTheSiteWithinTheWorkspace(): void
    {
        $this->setUpThreeSites();
        $GLOBALS['BE_USER']->workspace = 1;

        // In workspace 1 page 3 ("/about/team") was moved from the main site below the second site's root
        $state = RecordStateFactory::forName('pages')->fromArray(['pid' => 10, 'sys_language_uid' => 0, 't3ver_wsid' => 1], 10, 'NEW1');

        self::assertFalse($this->createSlugHelper(1)->isUniqueInSite('/about/team', $state));
    }

    #[Test]
    public function isUniqueInSiteIgnoresAPageDeletedWithinTheWorkspace(): void
    {
        $this->setUpThreeSites();
        $GLOBALS['BE_USER']->workspace = 1;

        // In workspace 1 page 21 ("/about/team" in the third site) is deleted, so the slug is free there
        $state = RecordStateFactory::forName('pages')->fromArray(['pid' => 20, 'sys_language_uid' => 0, 't3ver_wsid' => 1], 20, 'NEW1');

        self::assertTrue($this->createSlugHelper(1)->isUniqueInSite('/about/team', $state));
    }

    #[Test]
    public function isUniqueInSiteIgnoresPagesThatOnlyExistInAWorkspaceWhenCheckingLive(): void
    {
        $this->setUpThreeSites();

        // "/news" below the second site's root only exists as a new page in workspace 1
        $state = RecordStateFactory::forName('pages')->fromArray(['pid' => 10, 'sys_language_uid' => 0], 10, 'NEW1');

        self::assertTrue($this->createSlugHelper()->isUniqueInSite('/news', $state));
    }
}
