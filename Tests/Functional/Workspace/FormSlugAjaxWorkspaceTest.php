<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Workspace;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Controller\FormSlugAjaxController as CoreFormSlugAjaxController;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;
use Wazum\Sluggi\Tests\Functional\DataHandler\Fixtures\TestSlugPostModifier;

final class FormSlugAjaxWorkspaceTest extends FunctionalTestCase
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

        $GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['postModifiers'] = [
            TestSlugPostModifier::class . '->appendWorkspaceId',
        ];
        if (Typo3Compatibility::hasTcaSchemaFactory()) {
            GeneralUtility::makeInstance(TcaSchemaFactory::class)->load($GLOBALS['TCA'], true);
        }

        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_workspace_ajax_conflict.csv');
        $this->setUpSite();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function getOriginalSlugPassesWorkspaceIdToPostModifiers(): void
    {
        $backendUser = $GLOBALS['BE_USER'];
        $backendUser->workspace = 1;
        $this->get(Context::class)->setAspect('workspace', new WorkspaceAspect(1));

        $controller = GeneralUtility::makeInstance(CoreFormSlugAjaxController::class);

        $signature = Typo3Compatibility::hmac(
            'pages' . 1 . 3 . 0 . 'slugedit' . 1,
            CoreFormSlugAjaxController::class
        );

        $request = (new ServerRequest())
            ->withParsedBody([
                'tableName' => 'pages',
                'fieldName' => 'slug',
                'command' => 'edit',
                'pageId' => 1,
                'parentPageId' => 1,
                'recordId' => 3,
                'language' => 0,
                'signature' => $signature,
                'mode' => 'recreate',
                'values' => [
                    'title' => 'Existing Page',
                ],
            ]);

        $response = $controller->suggestAction($request);
        $data = json_decode((string)$response->getBody(), true);

        self::assertTrue($data['hasConflicts'] ?? false, 'Should detect slug conflict');
        self::assertStringContainsString(
            '-ws1',
            $data['slug'],
            'getOriginalSlug should pass workspace ID to SlugHelper so postModifiers receive it'
        );
    }
}
