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

final class SlugElementLastSegmentPrefixTest extends FunctionalTestCase
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
                'synchronize' => '0',
                'last_segment_only' => '1',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_last_segment_prefix.csv');
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
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
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
    public function existingPageWithLastSegmentOnlyRendersHierarchyBasedLockedPrefix(): void
    {
        $this->setUpBackendUser(2);

        // Page 3 has slug /wrong-prefix/child but parent (uid=2) has slug /parent
        // The lockedPrefix should be /parent (from hierarchy), not derived from the broken slug
        $html = $this->renderSlugElement(3);

        self::assertStringContainsString('locked-prefix="/parent"', $html);
        self::assertStringContainsString('last-segment-only', $html);
    }

    #[Test]
    public function editorGetsParentSlugAttribute(): void
    {
        $this->setUpBackendUser(2);

        $html = $this->renderSlugElement(3);

        self::assertStringContainsString('parent-slug="/parent"', $html);
    }
}
