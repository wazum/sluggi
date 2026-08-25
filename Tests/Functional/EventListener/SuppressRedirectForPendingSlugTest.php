<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class SuppressRedirectForPendingSlugTest extends FunctionalTestCase
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

    /**
     * The interesting case: a translation that is already visible when its slug is first
     * generated. TYPO3 hides a fresh translation (hideAtCopy), and hidden pages are
     * already covered by SuppressRedirectForUnpublishedPage — but a translation the
     * editor published before naming it, or one created with hideAtCopy switched off,
     * reaches this point visible, and only this listener keeps the placeholder path out
     * of the redirect table.
     */
    #[Test]
    public function generatingThePendingSlugOfAVisibleTranslationCreatesNoRedirect(): void
    {
        $this->saveFields(3, ['title' => 'Gesperrte Seite']);

        self::assertSame(
            '/gesperrte-seite',
            $this->fetchSlug(3),
            'The generation must happen, otherwise this test proves nothing',
        );
        self::assertSame(0, $this->countAllRedirects(), 'The placeholder path was never a public URL');
    }

    #[Test]
    public function generatingThePendingSlugOfAHiddenTranslationCreatesNoRedirect(): void
    {
        $this->saveFields(6, ['title' => 'Versteckte Uebersetzung']);

        self::assertSame(
            '/versteckte-uebersetzung',
            $this->fetchSlug(6),
            'The generation must happen, otherwise this test proves nothing',
        );
        self::assertSame(0, $this->countAllRedirects());
    }

    #[Test]
    public function renamingARegularPageStillCreatesARedirect(): void
    {
        $this->saveFields(4, ['slug' => '/normal-page-renamed']);

        self::assertSame(1, $this->countAllRedirects());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_pending_slug_redirect_test.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

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
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function saveFields(int $pageId, array $fields): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [$pageId => $fields]], []);
        $dataHandler->process_datamap();
    }

    private function fetchSlug(int $pageId): string
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('slug')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageId, \Doctrine\DBAL\ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        return (string)($row['slug'] ?? '');
    }

    private function countAllRedirects(): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable('sys_redirect')
            ->count('*', 'sys_redirect', []);
    }
}
