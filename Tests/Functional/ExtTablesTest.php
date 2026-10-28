<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ExtTablesTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
        __DIR__ . '/Fixtures/Extensions/sluggi_ext_tables_test',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    #[Test]
    public function extTablesOfExtensionsLoadedAfterSluggiRunFromTheCachedFile(): void
    {
        Bootstrap::loadExtTables();
        unset($GLOBALS['SLUGGI_EXT_TABLES_TEST_LOADED']);

        Bootstrap::loadExtTables();

        self::assertTrue($GLOBALS['SLUGGI_EXT_TABLES_TEST_LOADED'] ?? false);
    }
}
