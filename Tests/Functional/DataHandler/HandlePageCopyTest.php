<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class HandlePageCopyTest extends FunctionalTestCase
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
    public function slugIsUpdatedWhenPageIsCopiedIntoAnother(): void
    {
        $this->setUpTest('pages_for_copy.csv');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            'pages' => [
                3 => [
                    'copy' => 2,
                ],
            ],
        ]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_copy_into_parent.csv');
    }

    #[Test]
    public function copyingPageIntoParentWithExistingSlugCreatesUniqueSlug(): void
    {
        $this->setUpTest('pages_for_copy_duplicate.csv');

        // Copy page 5 (Parent B > Same Name with slug /parent-b/same-name) into Parent A (page 2)
        // Parent A already has a child with slug /parent-a/same-name
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            'pages' => [
                5 => [
                    'copy' => 2,
                ],
            ],
        ]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_copy_duplicate.csv');
    }

    #[Test]
    public function copyingPageCopiesTranslationsWithCorrectSlugs(): void
    {
        $this->setUpTest('pages_for_copy_translated.csv');

        // Copy DEFAULT language page 3 into page 2
        // This should also copy the German translation with correct translated parent slug
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            'pages' => [
                3 => [
                    'copy' => 2,
                ],
            ],
        ]);
        $dataHandler->process_cmdmap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_copy_translated.csv');
    }
}
