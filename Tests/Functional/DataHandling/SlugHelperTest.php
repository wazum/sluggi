<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandling;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
}
