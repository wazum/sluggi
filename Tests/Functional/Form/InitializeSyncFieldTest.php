<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Form;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class InitializeSyncFieldTest extends FunctionalTestCase
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
                'synchronize_default' => '1',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_sync_default.csv');
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function newPageHasSyncEnabledByDefault(): void
    {
        $this->setUpBackendUser(1);

        $request = (new ServerRequest('https://example.com/typo3/'))
            ->withAttribute('normalizedParams', NormalizedParams::createFromServerParams($_SERVER))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $formDataCompiler = GeneralUtility::makeInstance(FormDataCompiler::class);
        $formDataGroup = GeneralUtility::makeInstance(TcaDatabaseRecord::class);

        $result = $formDataCompiler->compile([
            'tableName' => 'pages',
            'command' => 'new',
            'vanillaUid' => 0,
            'parentPageRow' => ['uid' => 1],
            'request' => $request,
        ], $formDataGroup);

        self::assertSame(
            1,
            $result['databaseRow']['tx_sluggi_sync'],
            'New page should have tx_sluggi_sync=1 when synchronize and synchronize_default are enabled'
        );
    }
}
