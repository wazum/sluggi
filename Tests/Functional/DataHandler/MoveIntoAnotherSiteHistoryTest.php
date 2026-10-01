<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\History\RecordHistoryStore;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;
use Wazum\Sluggi\Tests\Functional\DataHandler\Fixtures\RecordingDataHandler;

final class MoveIntoAnotherSiteHistoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_move_into_another_site.csv');
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
                ],
                'settings' => [
                    'redirects' => [
                        'autoUpdateSlugs' => true,
                        'autoCreateRedirects' => false,
                    ],
                ],
            ]);
        }
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][DataHandler::class]);
        GeneralUtility::flushInternalRuntimeCaches();
        parent::tearDown();
    }

    #[Test]
    public function slugChangesOfMovedPageAndSubpagesAreInHistoryWhenMovedPageCollides(): void
    {
        $this->movePage(2, 10);

        self::assertSame('/about-1', $this->slugOf(2));
        self::assertSame('/about-1/team', $this->slugOf(3));
        self::assertSame('/about', $this->oldestHistorySlugOf(2));
        self::assertSame('/about/team', $this->oldestHistorySlugOf(3));
    }

    #[Test]
    public function slugChangesOfSubpagesAreInHistoryWhenOnlySubpageCollides(): void
    {
        $this->movePage(2, 20);

        self::assertSame('/about', $this->slugOf(2));
        self::assertSame('/about/team-1', $this->slugOf(3));
        self::assertSame('/about/team', $this->oldestHistorySlugOf(3));
    }

    #[Test]
    public function subpagesAreNotRewrittenWhenPageIsMovedWithinItsSite(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][DataHandler::class]['className'] = RecordingDataHandler::class;
        GeneralUtility::flushInternalRuntimeCaches();
        RecordingDataHandler::$writtenPageIds = [];

        $this->movePage(2, -4);

        self::assertNotContains(3, RecordingDataHandler::$writtenPageIds);
    }

    private function movePage(int $uid, int $target): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], ['pages' => [$uid => ['move' => $target]]]);
        $dataHandler->process_cmdmap();
    }

    private function slugOf(int $uid): string
    {
        return (string)$this->getConnectionPool()->getConnectionForTable('pages')
            ->select(['slug'], 'pages', ['uid' => $uid])->fetchOne();
    }

    private function oldestHistorySlugOf(int $uid): ?string
    {
        $rows = $this->getConnectionPool()->getConnectionForTable('sys_history')
            ->select(
                ['history_data'],
                'sys_history',
                ['tablename' => 'pages', 'recuid' => $uid, 'actiontype' => RecordHistoryStore::ACTION_MODIFY],
                [],
                ['uid' => 'ASC']
            )
            ->fetchFirstColumn();
        foreach ($rows as $historyData) {
            $data = json_decode((string)$historyData, true);
            if (isset($data['oldRecord']['slug'])) {
                return $data['oldRecord']['slug'];
            }
        }

        return null;
    }
}
