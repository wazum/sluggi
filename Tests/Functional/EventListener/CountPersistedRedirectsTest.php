<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Redirects\Event\AfterAutoCreateRedirectHasBeenPersistedEvent;
use TYPO3\CMS\Redirects\RedirectUpdate\PlainSlugReplacementRedirectSource;
use TYPO3\CMS\Redirects\RedirectUpdate\RedirectSourceCollection;
use TYPO3\CMS\Redirects\RedirectUpdate\SlugRedirectChangeItem;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;
use Wazum\Sluggi\EventListener\CountPersistedRedirects;
use Wazum\Sluggi\Service\SlugChangeReportStore;

final class CountPersistedRedirectsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    #[Test]
    public function incrementsForActivePersistedRedirect(): void
    {
        $this->saveFields(2, ['slug' => '/live-renamed']);

        $report = $this->getStore()->getReport();
        self::assertNotNull($report);
        self::assertSame(1, $report['redirectsCreated']);
    }

    #[Test]
    public function doesNotIncrementWhenHiddenPageSuppression(): void
    {
        $this->saveFields(3, ['slug' => '/draft-renamed']);

        $report = $this->getStore()->getReport();
        self::assertSame(0, $report['redirectsCreated']);
    }

    #[Test]
    public function doesNotIncrementWhenRedirectRowIsSoftDeleted(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('sys_redirect');
        $connection->insert('sys_redirect', [
            'source_path' => '/whatever',
            'target' => 't3://page?uid=2',
            'source_host' => '*',
            'is_regexp' => 0,
            'force_https' => 0,
            'respect_query_parameters' => 0,
            'creation_type' => 1,
            'deleted' => 1,
            'disabled' => 0,
        ]);
        $uid = (int)$connection->lastInsertId();

        $changeItem = new SlugRedirectChangeItem(
            defaultLanguagePageId: 2,
            pageId: 2,
            site: $this->createMock(Site::class),
            siteLanguage: $this->createMock(SiteLanguage::class),
            original: ['uid' => 2, 'slug' => '/old', 'sys_language_uid' => 0],
            sourcesCollection: new RedirectSourceCollection(),
            changed: ['uid' => 2, 'slug' => '/whatever', 'sys_language_uid' => 0],
        );
        $source = new PlainSlugReplacementRedirectSource('*', '/whatever', []);
        $event = new AfterAutoCreateRedirectHasBeenPersistedEvent(
            $changeItem,
            $source,
            ['uid' => $uid, 'source_path' => '/whatever', 'target' => 't3://page?uid=2'],
        );

        GeneralUtility::makeInstance(CountPersistedRedirects::class)($event);

        $report = $this->getStore()->getReport();
        self::assertNull($report, 'Listener must not initialize a report when the redirect row is soft-deleted.');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_count_redirects.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

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
            'settings' => [
                'redirects' => [
                    'autoUpdateSlugs' => true,
                    'autoCreateRedirects' => true,
                ],
            ],
        ]);
    }

    private function saveFields(int $pageId, array $fields): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [$pageId => $fields]], []);
        $dataHandler->process_datamap();
    }

    private function getStore(): SlugChangeReportStore
    {
        return GeneralUtility::makeInstance(SlugChangeReportStore::class);
    }
}
