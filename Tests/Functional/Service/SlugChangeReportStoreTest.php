<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Service\SlugChangeReportStore;

final class SlugChangeReportStoreTest extends FunctionalTestCase
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
        // setUpdateSignal() does nothing on the CLI
        $this->initializeEnvironment(false);
    }

    protected function tearDown(): void
    {
        $this->initializeEnvironment(true);
        parent::tearDown();
    }

    #[Test]
    public function updateSignalCarriesRedirectsCreatedAfterTheLastEntry(): void
    {
        $store = new SlugChangeReportStore();
        $store->addEntry(42, 'Page Forty-Two', []);

        $store->incrementPagesUpdated(2);
        $store->incrementRedirectsCreated(3);

        $signal = $GLOBALS['BE_USER']->getModuleData(BackendUtility::class . '::getUpdateSignal', 'ses');
        $parameter = $signal[SlugChangeReportStore::UPDATE_SIGNAL_KEY]['parameter'];

        self::assertSame(2, $parameter['pagesUpdated']);
        self::assertSame(3, $parameter['redirectsCreated']);
        self::assertSame(42, $parameter['entries'][0]['pageId']);
    }

    private function initializeEnvironment(bool $cli): void
    {
        Environment::initialize(
            Environment::getContext(),
            $cli,
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getCurrentScript(),
            Environment::isWindows() ? 'WINDOWS' : 'UNIX',
        );
    }
}
