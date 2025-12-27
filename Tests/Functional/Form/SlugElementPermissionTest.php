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

final class SlugElementPermissionTest extends FunctionalTestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_permission_test.csv');
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
        Typo3Compatibility::writeSiteConfiguration('test', $configuration);
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
    public function adminSeesAllFeatures(): void
    {
        $this->setUpBackendUser(1);
        $html = $this->renderSlugElement(2);

        self::assertStringContainsString('sync-feature-enabled', $html);
        self::assertStringContainsString('lock-feature-enabled', $html);
    }

    #[Test]
    public function userWithFullAccessSeesBothFeatures(): void
    {
        $this->setUpBackendUser(2);
        $html = $this->renderSlugElement(2);

        self::assertStringContainsString('sync-feature-enabled', $html);
        self::assertStringContainsString('lock-feature-enabled', $html);
    }

    #[Test]
    public function userWithSyncOnlyAccessSeesSyncFeature(): void
    {
        $this->setUpBackendUser(3);
        $html = $this->renderSlugElement(2);

        self::assertStringContainsString('sync-feature-enabled', $html);
        self::assertStringNotContainsString('lock-feature-enabled', $html);
    }

    #[Test]
    public function userWithLockOnlyAccessSeesLockFeature(): void
    {
        $this->setUpBackendUser(4);
        $html = $this->renderSlugElement(2);

        self::assertStringNotContainsString('sync-feature-enabled', $html);
        self::assertStringContainsString('lock-feature-enabled', $html);
    }

    #[Test]
    public function userWithoutSluggiAccessSeesNoFeatures(): void
    {
        $this->setUpBackendUser(5);
        $html = $this->renderSlugElement(2);

        self::assertStringNotContainsString('sync-feature-enabled', $html);
        self::assertStringNotContainsString('lock-feature-enabled', $html);
    }

    #[Test]
    public function userWithoutLockAccessStillSeesLockedStateOnLockedPage(): void
    {
        $this->setUpBackendUser(3); // sync_only user - no access to slug_locked field
        $html = $this->renderSlugElement(3); // locked page

        // User cannot toggle lock (no lock-feature-enabled)
        self::assertStringNotContainsString('lock-feature-enabled', $html);
        // But page IS locked - must be enforced visually
        self::assertStringContainsString('is-locked', $html);
    }

    #[Test]
    public function userWithoutSyncAccessStillSeesSyncedStateOnSyncedPage(): void
    {
        $this->setUpBackendUser(4); // lock_only user - no access to tx_sluggi_sync field
        $html = $this->renderSlugElement(4); // synced page

        // User cannot toggle sync (no sync-feature-enabled)
        self::assertStringNotContainsString('sync-feature-enabled', $html);
        // But page IS synced - must be enforced (auto-regeneration works)
        self::assertStringContainsString('is-synced', $html);
    }
}
