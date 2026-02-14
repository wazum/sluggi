<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Sync;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as CoreExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\SlugConfigurationService;
use Wazum\Sluggi\Service\SlugSyncService;

final class SlugSyncServiceTest extends TestCase
{
    #[Test]
    public function isSyncFeatureEnabledReturnsTrueWhenGlobalSyncEnabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('1');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig), new SlugConfigurationService(), $this->createMock(ConnectionPool::class));

        self::assertTrue($subject->isSyncFeatureEnabled());
    }

    #[Test]
    public function isSyncFeatureEnabledReturnsFalseWhenGlobalSyncDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('0');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig), new SlugConfigurationService(), $this->createMock(ConnectionPool::class));

        self::assertFalse($subject->isSyncFeatureEnabled());
    }

    #[Test]
    public function shouldSyncReturnsTrueWhenGlobalAndPageSyncEnabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('1');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig), new SlugConfigurationService(), $this->createMock(ConnectionPool::class));

        $result = $subject->shouldSync(['tx_sluggi_sync' => 1]);

        self::assertTrue($result);
    }

    #[Test]
    public function shouldSyncReturnsFalseWhenGlobalSyncDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('0');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig), new SlugConfigurationService(), $this->createMock(ConnectionPool::class));

        $result = $subject->shouldSync(['tx_sluggi_sync' => 1]);

        self::assertFalse($result);
    }

    #[Test]
    public function shouldSyncReturnsFalseWhenPageSyncDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('1');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig), new SlugConfigurationService(), $this->createMock(ConnectionPool::class));

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
        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig), new SlugConfigurationService(), $this->createMock(ConnectionPool::class));

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
        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig), new SlugConfigurationService(), $this->createMock(ConnectionPool::class));

        $result = $subject->hasSourceFieldChanged('pages', ['hidden' => 1]);

        self::assertFalse($result);
    }

    #[Test]
    public function shouldShowSourceBadgeReturnsTrueForNewPage(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('0');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig), new SlugConfigurationService(), $this->createMock(ConnectionPool::class));

        $result = $subject->shouldShowSourceBadge('pages', 'new', ['tx_sluggi_sync' => 0]);

        self::assertTrue($result);
    }

    #[Test]
    public function shouldShowSourceBadgeReturnsTrueForExistingPageWithSyncEnabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('1');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig), new SlugConfigurationService(), $this->createMock(ConnectionPool::class));

        $result = $subject->shouldShowSourceBadge('pages', 'edit', ['tx_sluggi_sync' => 1]);

        self::assertTrue($result);
    }

    #[Test]
    public function shouldShowSourceBadgeReturnsFalseForExistingPageWithSyncDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('1');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig), new SlugConfigurationService(), $this->createMock(ConnectionPool::class));

        $result = $subject->shouldShowSourceBadge('pages', 'edit', ['tx_sluggi_sync' => 0]);

        self::assertFalse($result);
    }

    #[Test]
    public function shouldShowSourceBadgeReturnsFalseForExistingPageWithGlobalSyncDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->with('sluggi', 'synchronize')->willReturn('0');

        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig), new SlugConfigurationService(), $this->createMock(ConnectionPool::class));

        $result = $subject->shouldShowSourceBadge('pages', 'edit', ['tx_sluggi_sync' => 1]);

        self::assertFalse($result);
    }

    #[Test]
    public function getEffectiveRecordSyncStateHandlesArrayLanguageFieldValues(): void
    {
        $GLOBALS['TCA']['tx_test']['ctrl'] = [
            'languageField' => 'sys_language_uid',
            'transOrigPointerField' => 'l10n_parent',
        ];

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connection = $this->createMock(\TYPO3\CMS\Core\Database\Connection::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $connectionPool->method('getConnectionForTable')->willReturn($connection);
        $connection->method('select')->willReturn($result);
        $result->method('fetchAssociative')->willReturn(['is_synced' => 0]);

        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig), new SlugConfigurationService(), $connectionPool);

        $record = [
            'uid' => 2,
            'sys_language_uid' => [1],
            'l10n_parent' => [1],
        ];

        $result = $subject->getEffectiveRecordSyncState('tx_test', $record);

        self::assertFalse($result, 'Must resolve array-form language fields and inherit parent sync state');
    }

    #[Test]
    public function pendingSyncStateOverridesDbState(): void
    {
        $connectionPool = $this->createMock(ConnectionPool::class);
        $connection = $this->createMock(\TYPO3\CMS\Core\Database\Connection::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $connectionPool->method('getConnectionForTable')->willReturn($connection);
        $connection->method('select')->willReturn($result);
        $result->method('fetchAssociative')->willReturn(['is_synced' => 1]);

        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $subject = new SlugSyncService(new ExtensionConfiguration($coreConfig), new SlugConfigurationService(), $connectionPool);

        $subject->setPendingSyncState('tx_test', 1, false);

        self::assertFalse(
            $subject->getRecordSyncState('tx_test', 1),
            'Pending state must override DB state'
        );
    }
}
