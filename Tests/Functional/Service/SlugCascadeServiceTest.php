<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Redirects\Service\SlugService;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;
use Wazum\Sluggi\Service\SlugCascadeService;

final class SlugCascadeServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['wazum/sluggi'];
    protected array $coreExtensionsToLoad = ['redirects', 'workspaces'];
    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => ['sluggi' => ['synchronize' => '1', 'lock' => '1']],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_update.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    private function setUpSite(bool $withGerman = false): void
    {
        $languages = [
            [
                'languageId' => 0,
                'title' => 'English',
                'locale' => 'en_US.UTF-8',
                'base' => '/',
            ],
        ];
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
            'settings' => ['redirects' => ['autoUpdateSlugs' => true, 'autoCreateRedirects' => true]],
        ]);
    }

    private function makeCorrelationId(): CorrelationId
    {
        return CorrelationId::forSubject(md5('test:' . time()))
            ->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'slug');
    }

    private function cascade(int $pageId, int &$updated = 0, int &$skipped = 0): void
    {
        $this->get(SlugCascadeService::class)->cascadeFromPage($pageId, $this->makeCorrelationId(), $updated, $skipped);
    }

    #[Test]
    public function regeneratesChildSlugBasedOnParentPath(): void
    {
        $updated = 0;
        $this->cascade(2, $updated);
        self::assertGreaterThan(0, $updated);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_recursive_update.csv');
    }

    #[Test]
    public function updatesGrandchildrenRecursively(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_update_deep.csv');
        $updated = 0;
        $this->cascade(2, $updated);
        self::assertGreaterThan(0, $updated);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_recursive_update_deep.csv');
    }

    #[Test]
    public function skipsLockedPages(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_locked.csv');
        $skipped = 0;
        $this->cascade(2, skipped: $skipped);
        self::assertGreaterThan(0, $skipped);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_recursive_locked.csv');
    }

    #[Test]
    public function skipsExcludedPageTypesButProcessesTheirChildren(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] = '199,254';
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_excluded_doktypes.csv');
        $skipped = 0;
        $this->cascade(2, skipped: $skipped);
        self::assertSame(2, $skipped);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_recursive_excluded_doktypes.csv');
    }

    #[Test]
    public function doesNotTriggerCoreSlugChangedNotification(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_update_deep.csv');
        $this->cascade(2);
        $hasSlugChangedSignal = false;
        foreach (BackendUtility::getUpdateSignalDetails() as $signal) {
            if (($signal['eventName'] ?? '') === 'slugChanged') {
                $hasSlugChangedSignal = true;
                break;
            }
        }
        self::assertFalse($hasSlugChangedSignal);
    }

    #[Test]
    public function updatesChildWithSyncDisabled(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_nosync.csv');
        $updated = 0;
        $this->cascade(2, $updated);
        self::assertGreaterThan(0, $updated);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_recursive_nosync.csv');
    }

    #[Test]
    public function updatesHiddenChildPages(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_hidden.csv');
        $updated = 0;
        $this->cascade(2, $updated);
        self::assertGreaterThan(0, $updated);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_recursive_hidden.csv');
    }

    #[Test]
    public function updatesTranslatedChildPages(): void
    {
        $this->setUpSite(withGerman: true);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_translated.csv');
        $updated = 0;
        $this->cascade(2, $updated);
        self::assertGreaterThan(0, $updated);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_recursive_translated.csv');
    }

    #[Test]
    public function skipsDescendantsOfLockedPageWhenLockDescendantsEnabled(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['lock_descendants'] = '1';
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_lock_descendants.csv');
        $this->cascade(2);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_recursive_lock_descendants.csv');
    }

    #[Test]
    public function usesWorkspaceOverlaidTitleForSlugGeneration(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_workspace_modified.csv');
        $GLOBALS['BE_USER']->workspace = 1;
        $this->get(Context::class)->setAspect('workspace', new WorkspaceAspect(1));
        $updated = 0;
        $this->cascade(2, $updated);
        $record = BackendUtility::getRecordWSOL('pages', 3, 'slug');
        self::assertSame('/parent/child-modified', $record['slug']);
        self::assertSame(1, $updated);
    }

    #[Test]
    public function processesPageMovedIntoSubtreeInWorkspace(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_workspace_moved_in.csv');
        $GLOBALS['BE_USER']->workspace = 1;
        $this->get(Context::class)->setAspect('workspace', new WorkspaceAspect(1));
        $updated = 0;
        $this->cascade(2, $updated);
        self::assertSame(2, $updated);
    }

    #[Test]
    public function skipsPageMovedOutOfSubtreeInWorkspace(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_workspace_moved_out.csv');
        $GLOBALS['BE_USER']->workspace = 1;
        $this->get(Context::class)->setAspect('workspace', new WorkspaceAspect(1));
        $updated = 0;
        $this->cascade(2, $updated);
        self::assertSame(1, $updated);
    }

    #[Test]
    public function skipsWorkspaceDeletedPages(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_workspace_deleted.csv');
        $GLOBALS['BE_USER']->workspace = 1;
        $this->get(Context::class)->setAspect('workspace', new WorkspaceAspect(1));
        $this->cascade(2);
        $deletedRecord = BackendUtility::getRecord('pages', 3, 'slug');
        self::assertSame('/old/child', $deletedRecord['slug']);
        $keptRecord = BackendUtility::getRecordWSOL('pages', 4, 'slug');
        self::assertSame('/parent/kept-in-ws', $keptRecord['slug']);
    }

    #[Test]
    public function slugChangesShareCorrelationIdInHistory(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_update_deep.csv');
        $correlationId = $this->makeCorrelationId();
        $updated = 0;
        $skipped = 0;
        $this->get(SlugCascadeService::class)->cascadeFromPage(2, $correlationId, $updated, $skipped);
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('sys_history');
        $count = (int)$queryBuilder
            ->count('*')
            ->from('sys_history')
            ->where($queryBuilder->expr()->eq('correlation_id', $queryBuilder->createNamedParameter((string)$correlationId)))
            ->executeQuery()
            ->fetchOne();
        self::assertGreaterThan(0, $count);
    }
}
