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

    #[Test]
    public function isLockDescendantsEnabledReturnsTrueWhenBothEnabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->willReturnMap([
                ['sluggi', 'lock', '1'],
                ['sluggi', 'lock_descendants', '1'],
            ]);

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertTrue($subject->isLockDescendantsEnabled());
    }

    #[Test]
    public function isLockDescendantsEnabledReturnsFalseWhenLockDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->willReturnMap([
                ['sluggi', 'lock', '0'],
                ['sluggi', 'lock_descendants', '1'],
            ]);

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertFalse($subject->isLockDescendantsEnabled());
    }

    #[Test]
    public function isLockDescendantsEnabledReturnsFalseWhenDescendantsDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->willReturnMap([
                ['sluggi', 'lock', '1'],
                ['sluggi', 'lock_descendants', '0'],
            ]);

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertFalse($subject->isLockDescendantsEnabled());
    }

    #[Test]
    public function isLockDescendantsEnabledReturnsFalseWhenSettingIsMissing(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->willThrowException(new Exception('Configuration not found'));

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertFalse($subject->isLockDescendantsEnabled());
    }

    #[Test]
    public function isFullPathEditingEnabledReturnsTrueWhenSettingIs1(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'allow_full_path_editing')
            ->willReturn('1');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertTrue($subject->isFullPathEditingEnabled());
    }

    #[Test]
    public function isFullPathEditingEnabledReturnsFalseWhenSettingIs0(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'allow_full_path_editing')
            ->willReturn('0');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertFalse($subject->isFullPathEditingEnabled());
    }

    #[Test]
    public function isFullPathEditingEnabledReturnsFalseWhenSettingIsMissing(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'allow_full_path_editing')
            ->willThrowException(new Exception('Configuration not found'));

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertFalse($subject->isFullPathEditingEnabled());
    }

    #[Test]
    public function getSynchronizeTablesReturnsArrayOfTableNames(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'synchronize_tables')
            ->willReturn('tx_news_domain_model_news,tx_blog_domain_model_post');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertSame(['tx_news_domain_model_news', 'tx_blog_domain_model_post'], $subject->getSynchronizeTables());
    }

    #[Test]
    public function getSynchronizeTablesTrimsWhitespace(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'synchronize_tables')
            ->willReturn(' tx_news_domain_model_news , tx_blog_domain_model_post ');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertSame(['tx_news_domain_model_news', 'tx_blog_domain_model_post'], $subject->getSynchronizeTables());
    }

    #[Test]
    public function getSynchronizeTablesReturnsEmptyArrayWhenEmpty(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'synchronize_tables')
            ->willReturn('');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertSame([], $subject->getSynchronizeTables());
    }

    #[Test]
    public function getSynchronizeTablesReturnsEmptyArrayWhenMissing(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'synchronize_tables')
            ->willThrowException(new Exception('Configuration not found'));

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertSame([], $subject->getSynchronizeTables());
    }

    #[Test]
    public function isTableSynchronizeEnabledReturnsTrueForConfiguredTable(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'synchronize_tables')
            ->willReturn('tx_news_domain_model_news,tx_blog_domain_model_post');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertTrue($subject->isTableSynchronizeEnabled('tx_news_domain_model_news'));
    }

    #[Test]
    public function isTableSynchronizeEnabledReturnsFalseForUnconfiguredTable(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'synchronize_tables')
            ->willReturn('tx_news_domain_model_news');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertFalse($subject->isTableSynchronizeEnabled('tx_blog_domain_model_post'));
    }

    #[Test]
    public function isSyncDefaultEnabledReturnsTrueWhenSettingIs1(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'synchronize_default')
            ->willReturn('1');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertTrue($subject->isSyncDefaultEnabled());
    }

    #[Test]
    public function isSyncDefaultEnabledReturnsFalseWhenSettingIs0(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'synchronize_default')
            ->willReturn('0');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertFalse($subject->isSyncDefaultEnabled());
    }

    #[Test]
    public function isSyncDefaultEnabledReturnsTrueWhenSettingIsMissing(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'synchronize_default')
            ->willThrowException(new Exception('Configuration not found'));

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertTrue($subject->isSyncDefaultEnabled());
    }

    #[Test]
    public function isCopyUrlEnabledReturnsTrueWhenSettingIs1(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'copy_url')
            ->willReturn('1');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertTrue($subject->isCopyUrlEnabled());
    }

    #[Test]
    public function isCopyUrlEnabledReturnsFalseWhenSettingIs0(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'copy_url')
            ->willReturn('0');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertFalse($subject->isCopyUrlEnabled());
    }

    #[Test]
    public function isCopyUrlEnabledReturnsFalseWhenSettingIsMissing(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'copy_url')
            ->willThrowException(new Exception('Configuration not found'));

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertFalse($subject->isCopyUrlEnabled());
    }

    #[Test]
    public function isCollapsedControlsEnabledReturnsTrueWhenSettingIs1(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'collapsed_controls')
            ->willReturn('1');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertTrue($subject->isCollapsedControlsEnabled());
    }

    #[Test]
    public function isCollapsedControlsEnabledReturnsFalseWhenSettingIs0(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'collapsed_controls')
            ->willReturn('0');

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertFalse($subject->isCollapsedControlsEnabled());
    }

    #[Test]
    public function isCollapsedControlsEnabledReturnsFalseWhenSettingIsMissing(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'collapsed_controls')
            ->willThrowException(new Exception('Configuration not found'));

        $subject = new ExtensionConfiguration($coreConfig);

        self::assertFalse($subject->isCollapsedControlsEnabled());
    }
}
