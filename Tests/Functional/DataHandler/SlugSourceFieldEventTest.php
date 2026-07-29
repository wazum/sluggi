<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class SlugSourceFieldEventTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
        __DIR__ . '/../Fixtures/Extensions/sluggi_event_test',
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_with_sync.csv');
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
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function aListenerCanReplaceTheValueTheUrlPathIsBuiltFrom(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    2 => [
                        'title' => 'Some Title Nobody Sees',
                        'slug' => '/test-page',
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $slug = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->select(['slug'], 'pages', ['uid' => 2])
            ->fetchOne();

        self::assertSame('/replaced-by-the-listener', $slug);
    }
}
