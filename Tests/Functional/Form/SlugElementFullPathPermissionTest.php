<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Form;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class SlugElementFullPathPermissionTest extends FunctionalTestCase
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
                'last_segment_only' => '1',
                'allow_full_path_editing' => '1',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_full_path_permission_test.csv');
        $this->setUpSite();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
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
        GeneralUtility::makeInstance(SiteWriter::class)->write('test', $configuration);
    }

    private function renderSlugElement(int $pageId): string
    {
        $request = (new ServerRequest('https://example.com/typo3/'))
            ->withAttribute('normalizedParams', NormalizedParams::createFromServerParams($_SERVER))
            ->withAttribute('applicationType', \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $formDataCompiler = GeneralUtility::makeInstance(FormDataCompiler::class);
        $formDataGroup = GeneralUtility::makeInstance(TcaDatabaseRecord::class);

        $formData = $formDataCompiler->compile([
            'tableName' => 'pages',
            'vanillaUid' => $pageId,
            'command' => 'edit',
            'request' => $request,
        ], $formDataGroup);

        $formData['renderType'] = 'sluggiSlug';
        $formData['fieldName'] = 'slug';
        $formData['parameterArray'] = [
            'itemFormElValue' => $formData['databaseRow']['slug'],
            'itemFormElName' => 'data[pages][' . $pageId . '][slug]',
            'fieldConf' => $formData['processedTca']['columns']['slug'],
        ];

        $nodeFactory = GeneralUtility::makeInstance(NodeFactory::class);
        $result = $nodeFactory->create($formData)->render();

        return $result['html'];
    }

    #[Test]
    public function userWithFullPathPermissionSeesToggle(): void
    {
        $this->setUpBackendUser(2);
        $html = $this->renderSlugElement(3);

        self::assertStringContainsString('full-path-feature-enabled', $html);
    }

    #[Test]
    public function userWithoutFullPathPermissionDoesNotSeeToggle(): void
    {
        $this->setUpBackendUser(3);
        $html = $this->renderSlugElement(3);

        self::assertStringNotContainsString('full-path-feature-enabled', $html);
    }

    #[Test]
    public function adminDoesNotSeeFullPathToggleBecauseNoRestrictionApplies(): void
    {
        $this->setUpBackendUser(1);
        $html = $this->renderSlugElement(3);

        self::assertStringNotContainsString('full-path-feature-enabled', $html);
    }
}
