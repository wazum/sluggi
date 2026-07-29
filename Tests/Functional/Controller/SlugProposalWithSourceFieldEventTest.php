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

final class SlugProposalWithSourceFieldEventTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
        __DIR__ . '/../Fixtures/Extensions/sluggi_event_test',
    ];

    protected array $coreExtensionsToLoad = ['redirects'];

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

    #[Test]
    public function theProposalShownInTheBackendUsesTheReplacedValue(): void
    {
        $controller = GeneralUtility::makeInstance(CoreFormSlugAjaxController::class);

        $response = $controller->suggestAction($this->buildSuggestRequest(
            recordId: 3,
            parentPageId: 2,
            values: ['title' => 'User Edited Title'],
        ));
        $data = json_decode((string)$response->getBody(), true);

        self::assertSame(
            '/parent/replaced-by-the-listener',
            $data['proposal'],
            'The preview has to show what the save will store',
        );
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
