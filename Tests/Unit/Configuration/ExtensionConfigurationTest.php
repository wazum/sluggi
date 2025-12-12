<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Configuration;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as CoreExtensionConfiguration;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;

final class ExtensionConfigurationTest extends TestCase
{
    #[Test]
    public function isSyncEnabledReturnsTrueWhenSettingIs1(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'synchronize')
            ->willReturn('1');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertTrue($subject->isSyncEnabled());
    }

    #[Test]
    public function isSyncEnabledReturnsFalseWhenSettingIs0(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'synchronize')
            ->willReturn('0');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertFalse($subject->isSyncEnabled());
    }

    #[Test]
    public function isSyncEnabledReturnsFalseWhenSettingIsMissing(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'synchronize')
            ->willThrowException(new Exception('Configuration not found'));

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertFalse($subject->isSyncEnabled());
    }

    #[Test]
    public function isLastSegmentOnlyEnabledReturnsTrueWhenSettingIs1(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'last_segment_only')
            ->willReturn('1');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertTrue($subject->isLastSegmentOnlyEnabled());
    }

    #[Test]
    public function isLastSegmentOnlyEnabledReturnsFalseWhenSettingIs0(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'last_segment_only')
            ->willReturn('0');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertFalse($subject->isLastSegmentOnlyEnabled());
    }

    #[Test]
    public function isLastSegmentOnlyEnabledReturnsFalseWhenSettingIsMissing(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'last_segment_only')
            ->willThrowException(new Exception('Configuration not found'));

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertFalse($subject->isLastSegmentOnlyEnabled());
    }
}
