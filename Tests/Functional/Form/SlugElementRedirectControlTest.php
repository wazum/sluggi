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

final class SlugElementRedirectControlTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_redirect_control_test.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
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
        Typo3Compatibility::writeSiteConfiguration('test', $configuration);
    }

    private function renderSlugElement(int $pageId, string $command = 'edit'): string
    {
        $request = (new ServerRequest('https://example.com/typo3/'))
            ->withAttribute('normalizedParams', NormalizedParams::createFromServerParams($_SERVER))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $formDataCompiler = GeneralUtility::makeInstance(FormDataCompiler::class);
        $formDataGroup = GeneralUtility::makeInstance(TcaDatabaseRecord::class);

        $formData = $formDataCompiler->compile([
            'tableName' => 'pages',
            'vanillaUid' => $pageId,
            'command' => $command,
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
    public function redirectControlAttributeIsPresentWhenFeatureEnabled(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['redirect_control'] = '1';

        $html = $this->renderSlugElement(2);

        self::assertStringContainsString('redirect-control', $html);
    }

    #[Test]
    public function redirectControlAttributeIsAbsentWhenFeatureDisabled(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['redirect_control'] = '0';

        $html = $this->renderSlugElement(2);

        self::assertStringNotContainsString('redirect-control', $html);
    }

    #[Test]
    public function redirectHiddenFieldIsPresentInRenderedHtml(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['redirect_control'] = '1';

        $html = $this->renderSlugElement(2);

        self::assertStringContainsString('sluggi-redirect-field', $html);
        self::assertStringContainsString('tx_sluggi_redirect', $html);
    }

    #[Test]
    public function redirectControlAttributeIsAbsentWhenSiteDisablesAutoCreateRedirects(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['redirect_control'] = '1';

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
            'settings' => [
                'redirects' => [
                    'autoCreateRedirects' => false,
                ],
            ],
        ]);

        $html = $this->renderSlugElement(2);

        self::assertStringNotContainsString('redirect-control', $html);
    }
}
