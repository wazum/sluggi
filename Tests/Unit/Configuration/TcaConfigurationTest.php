<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TcaConfigurationTest extends TestCase
{
    #[Test]
    public function pagesTcaDefinesSyncColumn(): void
    {
        $GLOBALS['TCA'] = [];

        require __DIR__ . '/../../../Configuration/TCA/Overrides/pages.php';

        self::assertArrayHasKey('tx_sluggi_sync', $GLOBALS['TCA']['pages']['columns']);
    }

    #[Test]
    public function syncColumnIsPassthroughType(): void
    {
        $GLOBALS['TCA'] = [];

        require __DIR__ . '/../../../Configuration/TCA/Overrides/pages.php';

        $config = $GLOBALS['TCA']['pages']['columns']['tx_sluggi_sync']['config'] ?? [];
        self::assertSame('passthrough', $config['type']);
    }
}
