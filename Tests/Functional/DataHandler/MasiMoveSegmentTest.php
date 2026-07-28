<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class MasiMoveSegmentTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
        'b13/masi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_masi_be_admin.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_masi_move_handcrafted.csv');
        Typo3Compatibility::writeSiteConfiguration('test', [
            'rootPageId' => 1,
            'base' => '/',
            'languages' => [[
                'languageId' => 0,
                'title' => 'English',
                'locale' => 'en_US.UTF-8',
                'base' => '/',
            ]],
            'settings' => ['redirects' => ['autoUpdateSlugs' => true, 'autoCreateRedirects' => false]],
        ]);
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function copyingAPageKeepsItsOwnSegment(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], ['pages' => [3 => ['copy' => 2]]]);
        $dataHandler->process_cmdmap();

        $slug = $this->getConnectionPool()->getConnectionForTable('pages')
            ->executeQuery('SELECT slug FROM pages WHERE pid = 2 AND uid <> 3')->fetchOne();

        self::assertSame('/target/hand-crafted', $slug);
    }

    #[Test]
    public function movingAPageKeepsItsOwnSegment(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], ['pages' => [3 => ['move' => 2]]]);
        $dataHandler->process_cmdmap();

        $slug = $this->getConnectionPool()->getConnectionForTable('pages')
            ->executeQuery('SELECT slug FROM pages WHERE uid = 3')->fetchOne();

        self::assertSame('/target/hand-crafted', $slug);
    }
}
