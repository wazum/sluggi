<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;
use Wazum\Sluggi\Controller\RecursiveSlugUpdateController;

final class RecursiveSlugUpdateControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['wazum/sluggi'];
    protected array $coreExtensionsToLoad = ['redirects', 'workspaces'];
    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => ['sluggi' => ['synchronize' => '1', 'lock' => '1']],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Service/Fixtures/pages_recursive_update.csv');
        Typo3Compatibility::writeSiteConfiguration('test', [
            'rootPageId' => 1,
            'base' => '/',
            'languages' => [[
                'languageId' => 0,
                'title' => 'English',
                'locale' => 'en_US.UTF-8',
                'base' => '/',
            ]],
            'settings' => ['redirects' => ['autoUpdateSlugs' => true, 'autoCreateRedirects' => true]],
        ]);
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    private function createRequest(int $pageId): ServerRequestInterface
    {
        return (new ServerRequest(new Uri('https://example.com/typo3/ajax/sluggi/recursive-slug-update')))
            ->withQueryParams(['id' => (string)$pageId]);
    }

    #[Test]
    public function rejectsNonAdminUser(): void
    {
        $this->setUpBackendUser(2);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        $response = $this->get(RecursiveSlugUpdateController::class)->updateAction($this->createRequest(2));
        self::assertSame(403, $response->getStatusCode());
        self::assertFalse(json_decode((string)$response->getBody(), true)['success']);
    }

    #[Test]
    public function returnsCorrelationIdsAndCountsInResponse(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Service/Fixtures/pages_recursive_update_deep.csv');
        $response = $this->get(RecursiveSlugUpdateController::class)->updateAction($this->createRequest(2));
        $body = json_decode((string)$response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($body['success']);
        self::assertArrayHasKey('updated', $body);
        self::assertArrayHasKey('skipped', $body);
        self::assertNotEmpty($body['correlations']['correlationIdSlugUpdate']);
        self::assertNotEmpty($body['correlations']['correlationIdRedirectCreation']);
    }
}
