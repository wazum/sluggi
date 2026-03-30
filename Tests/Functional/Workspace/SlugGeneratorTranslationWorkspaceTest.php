<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Workspace;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;
use Wazum\Sluggi\Service\SlugGeneratorService;

final class SlugGeneratorTranslationWorkspaceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
        'workspaces',
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
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_workspace_translation.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function getParentSlugReturnsWorkspaceOverlaidTranslationSlug(): void
    {
        $backendUser = $GLOBALS['BE_USER'];
        $backendUser->workspace = 1;
        $this->get(Context::class)->setAspect('workspace', new WorkspaceAspect(1));

        $service = $this->get(SlugGeneratorService::class);
        $slug = $service->getParentSlug(2, 1);

        self::assertSame('/eltern-ws', $slug);
    }

    #[Test]
    public function getParentSlugReturnsLiveTranslationSlugInLiveContext(): void
    {
        $service = $this->get(SlugGeneratorService::class);
        $slug = $service->getParentSlug(2, 1);

        self::assertSame('/eltern', $slug);
    }
}
