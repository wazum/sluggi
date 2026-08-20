<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Form;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class SlugSourceRichtextTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
        'typo3conf/ext/sluggi/Tests/Functional/Fixtures/Extensions/test_sluggi_records',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
        'rte_ckeditor',
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

        $this->applyRichtextSourceField();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/non_page_table_test.csv');
        $this->setUpSite();

        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    private function applyRichtextSourceField(): void
    {
        $GLOBALS['TCA']['tx_sluggitest_article']['columns']['title']['config'] = [
            'type' => 'text',
            'enableRichtext' => true,
            'richtextConfiguration' => 'default',
        ];
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

    private function renderSourceField(int $recordId, string $fieldName): string
    {
        $request = (new ServerRequest('https://example.com/typo3/'))
            ->withAttribute('normalizedParams', NormalizedParams::createFromServerParams($_SERVER))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $formDataCompiler = GeneralUtility::makeInstance(FormDataCompiler::class);
        $formDataGroup = GeneralUtility::makeInstance(TcaDatabaseRecord::class);

        $formData = $formDataCompiler->compile([
            'tableName' => 'tx_sluggitest_article',
            'vanillaUid' => $recordId,
            'command' => 'edit',
            'request' => $request,
        ], $formDataGroup);

        $fieldConfiguration = $formData['processedTca']['columns'][$fieldName];

        // Mirrors core's SingleFieldContainer node name resolution.
        $formData['renderType'] = $fieldConfiguration['config']['renderType'] ?? $fieldConfiguration['config']['type'];
        $formData['fieldName'] = $fieldName;
        $formData['parameterArray'] = [
            'itemFormElValue' => $formData['databaseRow'][$fieldName],
            'itemFormElName' => 'data[tx_sluggitest_article][' . $recordId . '][' . $fieldName . ']',
            'itemFormElID' => 'data_tx_sluggitest_article_' . $recordId . '_' . $fieldName,
            'fieldConf' => $fieldConfiguration,
        ];

        $nodeFactory = GeneralUtility::makeInstance(NodeFactory::class);

        return $nodeFactory->create($formData)->render()['html'];
    }

    #[Test]
    public function richtextSourceFieldKeepsItsEditor(): void
    {
        $this->setUpBackendUser(1);

        self::assertStringContainsString(
            'typo3-rte-ckeditor-ckeditor5',
            $this->renderSourceField(1, 'title'),
            'A slug source field with enableRichtext must still be rendered by the RTE.'
        );
    }
}
