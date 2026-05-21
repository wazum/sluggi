<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Controller\FormSlugAjaxController as CoreFormSlugAjaxController;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class FormSlugAjaxControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['wazum/sluggi'];

    protected array $coreExtensionsToLoad = ['redirects'];

    #[Test]
    public function recreateWithEmptyValuesFallsBackToPersistedSourceFields(): void
    {
        $controller = GeneralUtility::makeInstance(CoreFormSlugAjaxController::class);

        $response = $controller->suggestAction($this->buildSuggestRequest(recordId: 3, parentPageId: 2, values: []));
        $data = json_decode((string)$response->getBody(), true);

        self::assertStringNotContainsString('default-', (string)$data['proposal']);
        self::assertSame('/parent/full-path-save', $data['proposal']);
    }

    #[Test]
    public function recreateWithBlankTitleFallsBackToPersistedSourceFields(): void
    {
        $controller = GeneralUtility::makeInstance(CoreFormSlugAjaxController::class);

        $response = $controller->suggestAction($this->buildSuggestRequest(recordId: 3, parentPageId: 2, values: ['title' => '']));
        $data = json_decode((string)$response->getBody(), true);

        self::assertSame('/parent/full-path-save', $data['proposal']);
    }

    #[Test]
    public function submittedTitleStillWinsOverPersistedRecord(): void
    {
        $controller = GeneralUtility::makeInstance(CoreFormSlugAjaxController::class);

        $response = $controller->suggestAction($this->buildSuggestRequest(
            recordId: 3,
            parentPageId: 2,
            values: ['title' => 'User Edited Title'],
        ));
        $data = json_decode((string)$response->getBody(), true);

        self::assertSame('/parent/user-edited-title', $data['proposal']);
    }

    #[Test]
    public function conflictBranchReturnsOriginalSlugFromPersistedSourceFields(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_slug_suggest_conflict.csv');
        $controller = GeneralUtility::makeInstance(CoreFormSlugAjaxController::class);

        $response = $controller->suggestAction($this->buildSuggestRequest(recordId: 3, parentPageId: 2, values: []));
        $data = json_decode((string)$response->getBody(), true);

        self::assertTrue($data['hasConflicts'] ?? false);
        self::assertSame('/parent/full-path-save', $data['slug'] ?? null);
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (Typo3Compatibility::hasTcaSchemaFactory()) {
            GeneralUtility::makeInstance(TcaSchemaFactory::class)->load($GLOBALS['TCA'], true);
        }
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_slug_suggest_without_source.csv');
        Typo3Compatibility::writeSiteConfiguration('test', [
            'rootPageId' => 1,
            'base' => '/',
            'languages' => [[
                'languageId' => 0,
                'title' => 'English',
                'locale' => 'en_US.UTF-8',
                'base' => '/',
            ]],
        ]);
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    /**
     * @param array<string, mixed> $values
     */
    private function buildSuggestRequest(int $recordId, int $parentPageId, array $values): ServerRequest
    {
        $signature = Typo3Compatibility::hmac(
            'pages' . $parentPageId . $recordId . 0 . 'slugedit' . $parentPageId,
            CoreFormSlugAjaxController::class,
        );

        return (new ServerRequest())->withParsedBody([
            'tableName' => 'pages',
            'fieldName' => 'slug',
            'command' => 'edit',
            'pageId' => $parentPageId,
            'parentPageId' => $parentPageId,
            'recordId' => $recordId,
            'language' => 0,
            'signature' => $signature,
            'mode' => 'recreate',
            'values' => $values,
        ]);
    }
}
