<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Hooks;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class BackendAssetLoadingTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['wazum/sluggi'];
    protected array $coreExtensionsToLoad = ['redirects'];

    private function loadedJavaScriptModules(): string
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.com/typo3/'))
            ->withAttribute('normalizedParams', NormalizedParams::createFromServerParams($_SERVER))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $hook = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_pagerenderer.php']['render-preProcess']['sluggi'];
        $parameters = [];
        $hook($parameters, $pageRenderer);

        return (string)json_encode($pageRenderer->getJavaScriptRenderer()->toArray());
    }

    #[Test]
    public function slugChangeReportHandlerIsLoadedWithRedirectControlDisabled(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['redirect_control'] = '0';

        self::assertStringContainsString(
            'redirect-notification-handler',
            $this->loadedJavaScriptModules(),
        );
    }
}
