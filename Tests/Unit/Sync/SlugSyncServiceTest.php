<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Sync;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as CoreExtensionConfiguration;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\SlugSyncService;

final class SlugSyncServiceTest extends TestCase
{
    #[Test]
    public function isSyncFeatureEnabledReturnsTrueWhenGlobalSyncEnabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('1');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig));

        self::assertTrue($subject->isSyncFeatureEnabled());
    }

    #[Test]
    public function isSyncFeatureEnabledReturnsFalseWhenGlobalSyncDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('0');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig));

        self::assertFalse($subject->isSyncFeatureEnabled());
    }

    #[Test]
    public function shouldSyncReturnsTrueWhenGlobalAndPageSyncEnabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('1');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig));

        $result = $subject->shouldSync(['tx_sluggi_sync' => 1]);

        self::assertTrue($result);
    }

    #[Test]
    public function shouldSyncReturnsFalseWhenGlobalSyncDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('0');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig));

        $result = $subject->shouldSync(['tx_sluggi_sync' => 1]);

        self::assertFalse($result);
    }

    #[Test]
    public function shouldSyncReturnsFalseWhenPageSyncDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('1');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig));

        $result = $subject->shouldSync(['tx_sluggi_sync' => 0]);

        self::assertFalse($result);
    }

    #[Test]
    public function hasSourceFieldChangedReturnsTrueWhenConfiguredFieldChanged(): void
    {
        $GLOBALS['TCA']['pages']['columns']['slug']['config'] = [
            'type' => 'slug',
            'generatorOptions' => [
                'fields' => [['nav_title', 'title']],
            ],
        ];

        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig));

        $result = $subject->hasSourceFieldChanged('pages', ['title' => 'New Title']);

        self::assertTrue($result);
    }

    #[Test]
    public function hasSourceFieldChangedReturnsFalseWhenNoSourceFieldChanged(): void
    {
        $GLOBALS['TCA']['pages']['columns']['slug']['config'] = [
            'type' => 'slug',
            'generatorOptions' => [
                'fields' => [['nav_title', 'title']],
            ],
        ];

        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig));

        $result = $subject->hasSourceFieldChanged('pages', ['hidden' => 1]);

        self::assertFalse($result);
    }

    #[Test]
    public function shouldShowSourceBadgeReturnsTrueForNewPage(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('0');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig));

        $result = $subject->shouldShowSourceBadge('new', ['tx_sluggi_sync' => 0]);

        self::assertTrue($result);
    }

    #[Test]
    public function shouldShowSourceBadgeReturnsTrueForExistingPageWithSyncEnabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('1');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig));

        $result = $subject->shouldShowSourceBadge('edit', ['tx_sluggi_sync' => 1]);

        self::assertTrue($result);
    }

    #[Test]
    public function shouldShowSourceBadgeReturnsFalseForExistingPageWithSyncDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('1');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig));

        $result = $subject->shouldShowSourceBadge('edit', ['tx_sluggi_sync' => 0]);

        self::assertFalse($result);
    }

    #[Test]
    public function shouldShowSourceBadgeReturnsFalseForExistingPageWithGlobalSyncDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('0');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig));

        $result = $subject->shouldShowSourceBadge('edit', ['tx_sluggi_sync' => 1]);

        self::assertFalse($result);
    }
}
