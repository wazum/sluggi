<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\SlugSyncService;

final class ExtensionConfigurationTest extends FunctionalTestCase
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
            ],
        ],
    ];

    #[Test]
    public function isSyncEnabledReturnsTrueWhenConfiguredAsString1(): void
    {
        $subject = $this->get(ExtensionConfiguration::class);

        self::assertTrue($subject->isSyncEnabled());
    }

    #[Test]
    public function slugSyncServiceReportsSyncFeatureEnabled(): void
    {
        $subject = $this->get(SlugSyncService::class);

        self::assertTrue($subject->isSyncFeatureEnabled());
    }
}
