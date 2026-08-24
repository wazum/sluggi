<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class TrackPendingSlugTest extends FunctionalTestCase
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

    #[Test]
    public function localizingAPageMarksTheTranslationSlugAsPending(): void
    {
        $this->setUpTest('pages_locked_for_localize.csv', 1);

        $this->localize(2, 1);

        self::assertSame(1, $this->fetchTranslation(2, 1)['tx_sluggi_slug_pending']);
    }

    #[Test]
    public function localizingAsRestrictedEditorMarksTheTranslationSlugAsPending(): void
    {
        $this->setUpTest('pages_locked_for_localize_editor.csv', 2);

        $this->localize(2, 1);

        self::assertSame(1, $this->fetchTranslation(2, 1)['tx_sluggi_slug_pending']);
    }

    #[Test]
    public function regeneratingAPendingTranslationSlugConsumesThePendingFlag(): void
    {
        $this->setUpTest('pages_locked_translation_pending.csv', 1);

        $this->save(3, ['title' => 'Gesperrte Seite', 'slug' => '/translate-to-german-locked-page']);

        self::assertSame(0, $this->fetchTranslation(2, 1)['tx_sluggi_slug_pending']);
    }

    #[Test]
    public function aSaveWithoutSourceValueChangeKeepsThePendingFlag(): void
    {
        $this->setUpTest('pages_locked_translation_pending.csv', 1);

        $this->save(3, ['hidden' => 1]);

        $translation = $this->fetchTranslation(2, 1);
        self::assertSame('/translate-to-german-locked-page', $translation['slug']);
        self::assertSame(1, $translation['tx_sluggi_slug_pending'], 'The window must survive an unrelated save');
    }

    #[Test]
    public function changingOnlyTheFallbackFieldWhileThePreferredFieldStillHasThePlaceholderKeepsThePendingFlag(): void
    {
        $this->setUpTest('pages_locked_translation_pending_nav_title.csv', 1);
        $this->useFallbackChain();

        $this->save(3, ['title' => 'Gesperrte Seite']);

        $translation = $this->fetchTranslation(2, 1);
        self::assertSame('/translate-to-german-locked', $translation['slug']);
        self::assertSame(1, $translation['tx_sluggi_slug_pending'], 'The placeholder must not be confirmed by proxy');
    }

    #[Test]
    public function clearingThePreferredFieldMakesTheTranslatedFallbackEffectiveAndRegeneratesTheSlug(): void
    {
        $this->setUpTest('pages_locked_translation_pending_nav_title.csv', 1);
        $this->useFallbackChain();

        $this->save(3, ['title' => 'Gesperrte Seite', 'nav_title' => '']);

        $translation = $this->fetchTranslation(2, 1);
        self::assertSame('/gesperrte-seite', $translation['slug']);
        self::assertSame(0, $translation['tx_sluggi_slug_pending']);
    }

    #[Test]
    public function movingTheSourcePageRelocatesThePendingTranslationWithoutConsumingTheFlag(): void
    {
        $this->setUpTest('pages_locked_translation_pending_cascade.csv', 1);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], ['pages' => [3 => ['move' => 2]]]);
        $dataHandler->process_cmdmap();

        $translation = $this->fetchTranslation(3, 1);
        self::assertStringStartsWith(
            '/eltern-seite/',
            $translation['slug'],
            'The move must relocate the translation, otherwise this test proves nothing',
        );
        self::assertSame(1, $translation['tx_sluggi_slug_pending'], 'A relocation is not a confirmation');
    }

    private function useFallbackChain(): void
    {
        $GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['fields'] = [['nav_title', 'title']];
    }

    /**
     * @param array<string, mixed> $values
     */
    private function save(int $pageId, array $values): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [$pageId => $values]], []);
        $dataHandler->process_datamap();
    }

    private function setUpTest(string $fixture, int $backendUserId): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/' . $fixture);
        $this->setUpSite();
        $this->setUpBackendUser($backendUserId);
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
        ]);
    }

    private function localize(int $pageId, int $languageId): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            'pages' => [
                $pageId => [
                    'localize' => $languageId,
                ],
            ],
        ]);
        $dataHandler->process_cmdmap();
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchTranslation(int $parentPageId, int $languageId): array
    {
        // Localized pages are hidden at copy, so Connection::select() would filter them out.
        $row = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery(
                'SELECT uid, slug, tx_sluggi_slug_pending FROM pages WHERE l10n_parent = ? AND sys_language_uid = ?',
                [$parentPageId, $languageId],
            )
            ->fetchAssociative();

        self::assertIsArray($row, 'No translation found for page ' . $parentPageId);

        return $row;
    }
}
