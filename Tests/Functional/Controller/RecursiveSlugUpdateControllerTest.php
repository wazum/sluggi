<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;
use Wazum\Sluggi\Controller\RecursiveSlugUpdateController;

final class RecursiveSlugUpdateControllerTest extends FunctionalTestCase
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
                'lock' => '1',
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
                    'autoCreateRedirects' => true,
                ],
            ],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_update.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    private function createRequest(int $pageId): ServerRequestInterface
    {
        return (new ServerRequest(new Uri('https://example.com/typo3/ajax/sluggi/recursive-slug-update')))
            ->withQueryParams(['id' => (string)$pageId]);
    }

    #[Test]
    public function regeneratesChildSlugBasedOnParentPath(): void
    {
        $controller = $this->get(RecursiveSlugUpdateController::class);
        $response = $controller->updateAction($this->createRequest(2));

        self::assertSame(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        self::assertTrue($body['success']);
        self::assertGreaterThan(0, $body['updated']);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_recursive_update.csv');
    }

    #[Test]
    public function updatesGrandchildrenRecursively(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_update_deep.csv');

        $controller = $this->get(RecursiveSlugUpdateController::class);
        $response = $controller->updateAction($this->createRequest(2));

        self::assertSame(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        self::assertTrue($body['success']);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_recursive_update_deep.csv');
    }

    #[Test]
    public function skipsLockedPages(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_locked.csv');

        $controller = $this->get(RecursiveSlugUpdateController::class);
        $response = $controller->updateAction($this->createRequest(2));

        $body = json_decode((string)$response->getBody(), true);
        self::assertTrue($body['success']);
        self::assertGreaterThan(0, $body['skipped']);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_recursive_locked.csv');
    }

    #[Test]
    public function doesNotTriggerCoreSlugChangedNotification(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_update_deep.csv');

        $controller = $this->get(RecursiveSlugUpdateController::class);
        $controller->updateAction($this->createRequest(2));

        $updateSignals = BackendUtility::getUpdateSignalDetails();
        $hasSlugChangedSignal = false;
        foreach ($updateSignals as $signal) {
            if (($signal['eventName'] ?? '') === 'slugChanged') {
                $hasSlugChangedSignal = true;
                break;
            }
        }

        self::assertFalse($hasSlugChangedSignal, 'Core slugChanged notification should not be triggered');
    }

    #[Test]
    public function returnsSharedCorrelationIdsForUndo(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_update_deep.csv');

        $controller = $this->get(RecursiveSlugUpdateController::class);
        $response = $controller->updateAction($this->createRequest(2));

        $body = json_decode((string)$response->getBody(), true);
        self::assertArrayHasKey('correlations', $body);
        self::assertNotEmpty($body['correlations']['correlationIdSlugUpdate']);
        self::assertNotEmpty($body['correlations']['correlationIdRedirectCreation']);

        $correlationIdSlugUpdate = $body['correlations']['correlationIdSlugUpdate'];

        $connectionPool = $this->get(ConnectionPool::class);
        $queryBuilder = $connectionPool->getQueryBuilderForTable('sys_history');
        $historyEntries = $queryBuilder
            ->count('*')
            ->from('sys_history')
            ->where(
                $queryBuilder->expr()->eq(
                    'correlation_id',
                    $queryBuilder->createNamedParameter($correlationIdSlugUpdate),
                ),
            )
            ->executeQuery()
            ->fetchOne();

        self::assertGreaterThan(0, (int)$historyEntries, 'All slug changes should share the same correlation ID');
    }

    #[Test]
    public function updatesChildWithSyncDisabled(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_nosync.csv');

        $controller = $this->get(RecursiveSlugUpdateController::class);
        $response = $controller->updateAction($this->createRequest(2));

        $body = json_decode((string)$response->getBody(), true);
        self::assertTrue($body['success']);
        self::assertGreaterThan(0, $body['updated'], 'Child with sync disabled should still be updated');

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_recursive_nosync.csv');
    }

    #[Test]
    public function updatesHiddenChildPages(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_hidden.csv');

        $controller = $this->get(RecursiveSlugUpdateController::class);
        $response = $controller->updateAction($this->createRequest(2));

        $body = json_decode((string)$response->getBody(), true);
        self::assertTrue($body['success']);
        self::assertGreaterThan(0, $body['updated'], 'Hidden child pages should still be updated');

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_recursive_hidden.csv');
    }

    #[Test]
    public function updatesTranslatedChildPages(): void
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
            'settings' => [
                'redirects' => [
                    'autoUpdateSlugs' => true,
                    'autoCreateRedirects' => true,
                ],
            ],
        ]);

        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_translated.csv');

        $controller = $this->get(RecursiveSlugUpdateController::class);
        $response = $controller->updateAction($this->createRequest(2));

        $body = json_decode((string)$response->getBody(), true);
        self::assertTrue($body['success']);
        self::assertGreaterThan(0, $body['updated'], 'Translated child pages should be updated');

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_recursive_translated.csv');
    }

    #[Test]
    public function skipsDescendantsOfLockedPageWhenLockDescendantsEnabled(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['lock_descendants'] = '1';

        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_recursive_lock_descendants.csv');

        $controller = $this->get(RecursiveSlugUpdateController::class);
        $response = $controller->updateAction($this->createRequest(2));

        $body = json_decode((string)$response->getBody(), true);
        self::assertTrue($body['success']);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_recursive_lock_descendants.csv');
    }
}
