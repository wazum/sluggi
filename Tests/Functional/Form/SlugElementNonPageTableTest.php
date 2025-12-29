<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Form;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class SlugElementNonPageTableTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
        'typo3conf/ext/sluggi/Tests/Functional/Fixtures/Extensions/test_sluggi_records',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'sluggi' => [
                'synchronize' => '1',
                'synchronize_tables' => 'tx_sluggitest_article',
                'lock' => '1',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->applySluggiRenderType();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/non_page_table_test.csv');
        $this->setUpSite();

        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    private function applySluggiRenderType(): void
    {
        $GLOBALS['TCA']['tx_sluggitest_article']['columns']['slug']['config']['renderType'] = 'sluggiSlug';
    }

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
        ];
        Typo3Compatibility::writeSiteConfiguration('test', $configuration);
    }

    private function renderSlugElement(int $recordId): string
    {
        $request = (new ServerRequest('https://example.com/typo3/'))
            ->withAttribute('normalizedParams', NormalizedParams::createFromServerParams($_SERVER))
            ->withAttribute('applicationType', \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $formDataCompiler = GeneralUtility::makeInstance(FormDataCompiler::class);
        $formDataGroup = GeneralUtility::makeInstance(TcaDatabaseRecord::class);

        $formData = $formDataCompiler->compile([
            'tableName' => 'tx_sluggitest_article',
            'vanillaUid' => $recordId,
            'command' => 'edit',
            'request' => $request,
        ], $formDataGroup);

        $formData['renderType'] = 'sluggiSlug';
        $formData['fieldName'] = 'slug';
        $formData['parameterArray'] = [
            'itemFormElValue' => $formData['databaseRow']['slug'],
            'itemFormElName' => 'data[tx_sluggitest_article][' . $recordId . '][slug]',
            'fieldConf' => $formData['processedTca']['columns']['slug'],
        ];

        $nodeFactory = GeneralUtility::makeInstance(NodeFactory::class);
        $result = $nodeFactory->create($formData)->render();

        return $result['html'];
    }

    #[Test]
    public function nonPageTableWithAutoSyncDoesNotShowSyncToggle(): void
    {
        $this->setUpBackendUser(1);
        $html = $this->renderSlugElement(1);

        self::assertStringNotContainsString('sync-feature-enabled', $html);
    }

    #[Test]
    public function nonPageTableWithAutoSyncDoesNotShowLockToggle(): void
    {
        $this->setUpBackendUser(1);
        $html = $this->renderSlugElement(1);

        self::assertStringNotContainsString('lock-feature-enabled', $html);
    }

    #[Test]
    public function nonPageTableWithAutoSyncShowsIsSyncedState(): void
    {
        $this->setUpBackendUser(1);
        $html = $this->renderSlugElement(1);

        self::assertStringContainsString('is-synced', $html);
    }
}
