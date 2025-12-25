<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Lock;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as CoreExtensionConfiguration;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\SlugLockService;

final class SlugLockServiceTest extends TestCase
{
    #[Test]
    public function isLockFeatureEnabledReturnsTrueWhenGlobalLockEnabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'lock')->willReturn('1');

        $subject = new SlugLockService(new ExtensionConfiguration($coreConfig));

        self::assertTrue($subject->isLockFeatureEnabled());
    }

    #[Test]
    public function isLockFeatureEnabledReturnsFalseWhenGlobalLockDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'lock')->willReturn('0');

        $subject = new SlugLockService(new ExtensionConfiguration($coreConfig));

        self::assertFalse($subject->isLockFeatureEnabled());
    }

    #[Test]
    public function isLockedReturnsTrueWhenGlobalAndPageLockEnabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'lock')->willReturn('1');

        $subject = new SlugLockService(new ExtensionConfiguration($coreConfig));

        $result = $subject->isLocked(['slug_locked' => 1]);

        self::assertTrue($result);
    }

    #[Test]
    public function isLockedReturnsFalseWhenGlobalLockDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'lock')->willReturn('0');

        $subject = new SlugLockService(new ExtensionConfiguration($coreConfig));

        $result = $subject->isLocked(['slug_locked' => 1]);

        self::assertFalse($result);
    }

    #[Test]
    public function isLockedReturnsFalseWhenPageLockDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'lock')->willReturn('1');

        $subject = new SlugLockService(new ExtensionConfiguration($coreConfig));

        $result = $subject->isLocked(['slug_locked' => 0]);

        self::assertFalse($result);
    }

    #[Test]
    public function isLockedReturnsFalseWhenSlugLockedFieldMissing(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'lock')->willReturn('1');

        $subject = new SlugLockService(new ExtensionConfiguration($coreConfig));

        $result = $subject->isLocked([]);

        self::assertFalse($result);
    }

    #[Test]
    public function hasLockedAncestorReturnsFalseWhenFeatureDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->willReturnMap([
                ['sluggi', 'lock', '0'],
                ['sluggi', 'lock_descendants', '0'],
            ]);

        $subject = new SlugLockService(new ExtensionConfiguration($coreConfig));

        self::assertFalse($subject->hasLockedAncestor(123));
    }
}
