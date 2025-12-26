<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Form\FormDataProvider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as CoreExtensionConfiguration;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Form\FormDataProvider\InitializeSyncField;

final class InitializeSyncFieldTest extends TestCase
{
    #[Test]
    public function addDataSetsSyncTo1WhenSyncEnabledAndDefaultEnabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->willReturnMap([
            ['sluggi', 'synchronize', '1'],
            ['sluggi', 'synchronize_default', '1'],
        ]);

        $subject = new InitializeSyncField(new ExtensionConfiguration($coreConfig));
        $result = $subject->addData([
            'command' => 'new',
            'tableName' => 'pages',
            'databaseRow' => [],
        ]);

        self::assertSame(1, $result['databaseRow']['tx_sluggi_sync']);
    }

    #[Test]
    public function addDataDoesNotSetSyncWhenSyncEnabledButDefaultDisabled(): void
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')->willReturnMap([
            ['sluggi', 'synchronize', '1'],
            ['sluggi', 'synchronize_default', '0'],
        ]);

        $subject = new InitializeSyncField(new ExtensionConfiguration($coreConfig));
        $result = $subject->addData([
            'command' => 'new',
            'tableName' => 'pages',
            'databaseRow' => [],
        ]);

        self::assertArrayNotHasKey('tx_sluggi_sync', $result['databaseRow']);
    }
}
