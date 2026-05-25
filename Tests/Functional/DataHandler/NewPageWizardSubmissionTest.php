<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class NewPageWizardSubmissionTest extends FunctionalTestCase
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
                'synchronize_default' => '1',
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
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_new_page.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function newPageWithEmptyTitleAndSlugIsRejected(): void
    {
        // Simulates the v14 page wizard posting after the empty-value filter:
        // sync=1, doktype, pid, but no title and no slug.
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW1' => [
                        'pid' => 1,
                        'doktype' => 1,
                        'tx_sluggi_sync' => 1,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_for_new_page.csv');
        self::assertNotEmpty(
            $dataHandler->errorLog,
            'DataHandler must log an error when sluggi rejects the empty new page.',
        );
    }

    #[Test]
    public function doesNotInterveneWhenSourceFieldKeyIsExplicitlyPresent(): void
    {
        // Scope guard: a present-but-empty source-field key signals an
        // explicit submission (not the wizard's empty-value strip), so the
        // hook must defer even though the value is empty.
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [
                'pages' => [
                    'NEW1' => [
                        'pid' => 1,
                        'doktype' => 1,
                        'title' => '',
                        'tx_sluggi_sync' => 1,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertNotEmpty(
            $dataHandler->substNEWwithIDs,
            'Hook must defer when a source-field key is explicitly present.',
        );
    }

    #[Test]
    public function doesNotInterveneWhenDefaultsWouldSupplySourceFieldValue(): void
    {
        // Scope guard: TCA / userTS TCAdefaults can populate the source
        // field after this hook runs, so the hook must defer when such a
        // default exists rather than rejecting based on raw incoming data.
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->defaultValues['pages']['title'] = 'Default From UserTS';
        $dataHandler->start(
            [
                'pages' => [
                    'NEW1' => [
                        'pid' => 1,
                        'doktype' => 1,
                        'tx_sluggi_sync' => 1,
                    ],
                ],
            ],
            []
        );
        $dataHandler->process_datamap();

        self::assertNotEmpty(
            $dataHandler->substNEWwithIDs,
            'Hook must defer to TCA / userTS defaults that supply the source field.',
        );
    }
}
