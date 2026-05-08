<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class HandlePageUpdateHierarchyGuardTest extends FunctionalTestCase
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
                'last_segment_only' => '0',
            ],
        ],
    ];

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
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_hierarchy_guard.csv');
        $this->setUpSite();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    private function changeTitle(int $userUid, int $pageUid, string $newTitle): DataHandler
    {
        $this->setUpBackendUser($userUid);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'pages' => [
                $pageUid => ['title' => $newTitle],
            ],
        ], []);
        $dataHandler->process_datamap();

        return $dataHandler;
    }

    #[Test]
    public function nonAdminTitleChangeOnDivergedPageDoesNotRegenerateSlug(): void
    {
        $dataHandler = $this->changeTitle(2, 3, 'New Title');

        $row = $this->getConnectionPool()->getConnectionForTable('pages')
            ->select(['title', 'slug'], 'pages', ['uid' => 3])
            ->fetchAssociative();
        self::assertSame('New Title', $row['title']);
        self::assertSame('/testordner-url-aenderung2/testseite-kacheln-ines-alles', $row['slug']);
        self::assertSame([], $dataHandler->errorLog);
    }

    #[Test]
    public function adminTitleChangeOnDivergedPageRegeneratesSlugNormally(): void
    {
        $this->changeTitle(1, 3, 'Admin Renamed');

        $row = $this->getConnectionPool()->getConnectionForTable('pages')
            ->select(['title', 'slug'], 'pages', ['uid' => 3])
            ->fetchAssociative();
        self::assertSame('Admin Renamed', $row['title']);
        self::assertSame('/versteckte-oes/admin-renamed', $row['slug']);
    }

    #[Test]
    public function nonAdminTitleChangeOnInHierarchyPageRegeneratesSlugNormally(): void
    {
        $this->changeTitle(2, 5, 'Renamed');

        $row = $this->getConnectionPool()->getConnectionForTable('pages')
            ->select(['title', 'slug'], 'pages', ['uid' => 5])
            ->fetchAssociative();
        self::assertSame('Renamed', $row['title']);
        self::assertSame('/in-hierarchy/renamed', $row['slug']);
    }
}
