<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;
use Wazum\Sluggi\Service\SlugGeneratorService;

final class SlugGeneratorServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    /**
     * Language 2 has no translations of its own and falls back to language 1.
     */
    private function setUpSiteWithFallbackLanguage(): void
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
                [
                    'languageId' => 2,
                    'title' => 'Austrian German',
                    'locale' => 'de_AT.UTF-8',
                    'base' => '/at/',
                    'fallbackType' => 'fallback',
                    'fallbacks' => '1',
                ],
            ],
        ]);
    }

    #[Test]
    public function parentSlugFollowsTheConfiguredFallbackLanguageChain(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_language_fallback_parent.csv');
        $this->setUpSiteWithFallbackLanguage();
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');

        $parentSlug = $this->get(SlugGeneratorService::class)->getParentSlug(2, 2);

        self::assertSame('/bereich', $parentSlug);
    }
}
