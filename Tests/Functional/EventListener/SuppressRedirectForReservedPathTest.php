<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class SuppressRedirectForReservedPathTest extends FunctionalTestCase
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
        $this->importCSVDataSet(__DIR__ . '/../DataHandler/Fixtures/pages_reserved_path.csv');
        Typo3Compatibility::writeSiteConfiguration('test', [
            'rootPageId' => 1,
            'base' => '/',
            'languages' => [[
                'languageId' => 0,
                'title' => 'English',
                'locale' => 'en_US.UTF-8',
                'base' => '/',
            ]],
            'settings' => [
                'sluggi' => [
                    'reservedPaths' => ['/api'],
                ],
            ],
        ]);
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    private function countRedirectsForSource(string $path): int
    {
        return (int)GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_redirect')
            ->count('*', 'sys_redirect', ['source_path' => $path]);
    }

    #[Test]
    public function changingPageSlugFromReservedValueDoesNotCreateRedirect(): void
    {
        // Page 3 is grandfathered at /api. Rename it to /legacy-api.
        // No redirect from /api should be created.
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [3 => ['slug' => '/legacy-api']]], []);
        $dataHandler->process_datamap();

        self::assertSame(0, $this->countRedirectsForSource('/api'));
    }

    #[Test]
    public function changingPageSlugFromNonReservedValueCreatesRedirectNormally(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [2 => ['slug' => '/about-us']]], []);
        $dataHandler->process_datamap();

        self::assertSame(1, $this->countRedirectsForSource('/about'));
    }
}
