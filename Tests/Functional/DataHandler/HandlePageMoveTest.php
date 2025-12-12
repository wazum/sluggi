<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class HandlePageMoveTest extends FunctionalTestCase
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
                    'autoCreateRedirects' => false,
                ],
            ],
        ];
        GeneralUtility::makeInstance(SiteWriter::class)->write('test', $configuration);
    }

    private function setUpTest(string $fixture): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/' . $fixture);
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function slugIsUpdatedWhenPageIsMovedIntoAnother(): void
    {
        $this->setUpTest('pages_for_move.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            'pages' => [
                3 => [
                    'move' => 2,
                ],
            ],
        ]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_move_into_parent.csv');
    }

    #[Test]
    public function slugIsUpdatedWhenNestedPageIsMovedToRoot(): void
    {
        $this->setUpTest('pages_nested_for_move.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            'pages' => [
                3 => [
                    'move' => 1,
                ],
            ],
        ]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_move_to_root.csv');
    }

    #[Test]
    public function movingPageIntoParentWithExistingSlugCreatesUniqueSlug(): void
    {
        $this->setUpTest('pages_for_move_duplicate.csv');

        // Move page 5 (Parent B > Same Name) into Parent A (page 2)
        // Parent A already has a child with slug /parent-a/same-name
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            'pages' => [
                5 => [
                    'move' => 2,
                ],
            ],
        ]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_move_duplicate.csv');
    }

    #[Test]
    public function movingPageUpdatesTranslationSlugWithTranslatedParent(): void
    {
        $this->setUpTest('pages_for_move_translated.csv');

        // Move DEFAULT language page 3 into page 2
        // This should also update the German translation (page 4)
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            'pages' => [
                3 => [
                    'move' => 2,
                ],
            ],
        ]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_move_translated.csv');
    }
}
