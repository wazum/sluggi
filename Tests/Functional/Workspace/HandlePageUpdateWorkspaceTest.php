<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Workspace;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class HandlePageUpdateWorkspaceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
        'workspaces',
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
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_workspace_update.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function slugIsRegeneratedInWorkspaceWhenTitleChanges(): void
    {
        $backendUser = $GLOBALS['BE_USER'];
        $backendUser->workspace = 1;
        $this->get(Context::class)->setAspect('workspace', new WorkspaceAspect(1));

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'pages' => [
                3 => [
                    'title' => 'Updated Child',
                    'slug' => '/parent/updated-child',
                ],
            ],
        ], []);
        $dataHandler->process_datamap();

        $record = BackendUtility::getRecordWSOL('pages', 3, 'slug');
        self::assertSame('/parent/updated-child', $record['slug']);

        $liveRecord = BackendUtility::getRecord('pages', 3, 'slug');
        self::assertSame('/parent/child', $liveRecord['slug']);
    }
}
