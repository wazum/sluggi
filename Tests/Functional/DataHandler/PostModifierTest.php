<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Tests\Functional\DataHandler\Fixtures\TestSlugPostModifier;

/**
 * Tests for GitHub Issue #121: PostModifiers bypassed on page operations.
 *
 * When pages are moved or copied, sluggi's combineWithParent() previously
 * just concatenated strings without applying configured postModifiers.
 * This test verifies that postModifiers are now properly applied.
 *
 * @see https://github.com/wazum/sluggi/issues/121
 */
final class PostModifierTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    private function setUpSite(): void
    {
        $configuration = [
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
                    'autoCreateRedirects' => false,
                ],
            ],
        ];
        GeneralUtility::makeInstance(SiteWriter::class)->write('test', $configuration);
    }

    private function setUpTestWithPostModifier(string $fixture): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/' . $fixture);
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');

        $GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['postModifiers'] = [
            TestSlugPostModifier::class . '->stripPrefix',
        ];

        // Force TcaSchemaFactory to rebuild with our modified TCA
        GeneralUtility::makeInstance(TcaSchemaFactory::class)->load($GLOBALS['TCA'], true);
    }

    #[Test]
    public function postModifierIsAppliedWhenPageIsMoved(): void
    {
        $this->setUpTestWithPostModifier('pages_for_postmodifier_move.csv');

        // Move page 4 from under "Other Parent" (uid=3) to "Strip Parent" (uid=2)
        // Without postModifier: /strip/page-to-move
        // With postModifier: /page-to-move (because /strip prefix is stripped)
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            'pages' => [
                4 => [
                    'move' => 2,
                ],
            ],
        ]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_postmodifier_move.csv');
    }

    #[Test]
    public function postModifierIsAppliedWhenPageIsCopied(): void
    {
        $this->setUpTestWithPostModifier('pages_for_postmodifier_copy.csv');

        // Copy page 4 into "Strip Parent" (uid=2)
        // Without postModifier: /strip/page-to-copy
        // With postModifier: /page-to-copy (because /strip prefix is stripped)
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            'pages' => [
                4 => [
                    'copy' => 2,
                ],
            ],
        ]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_postmodifier_copy.csv');
    }

    #[Test]
    public function postModifierIsAppliedWhenNewPageIsCreated(): void
    {
        $this->setUpTestWithPostModifier('pages_for_postmodifier_new.csv');

        // Create a new page under "Strip Parent" (uid=2, slug=/strip)
        // Without postModifier: /strip/new-child-page
        // With postModifier: /new-child-page (because /strip prefix is stripped)
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'pages' => [
                'NEW1' => [
                    'pid' => 2,
                    'title' => 'New Child Page',
                    'doktype' => 1,
                ],
            ],
        ], []);
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_postmodifier_new.csv');
    }
}
