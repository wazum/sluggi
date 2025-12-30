<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Sluggi\Utility\SlugUtility;

final class SlugUtilityTest extends TestCase
{
    /**
     * @return array<string, array{slug: string, expected: string}>
     */
    public static function lastSegmentDataProvider(): array
    {
        return [
            'multi-segment slug' => [
                'slug' => '/parent/child',
                'expected' => 'child',
            ],
            'single segment' => [
                'slug' => '/page',
                'expected' => 'page',
            ],
            'empty string' => [
                'slug' => '',
                'expected' => '',
            ],
            'trailing slash' => [
                'slug' => '/parent/child/',
                'expected' => 'child',
            ],
            'deeply nested' => [
                'slug' => '/a/b/c/d/e',
                'expected' => 'e',
            ],
            'root slash only' => [
                'slug' => '/',
                'expected' => '',
            ],
        ];
    }

    #[Test]
    #[DataProvider('lastSegmentDataProvider')]
    public function getLastSegmentReturnsExpectedResult(string $slug, string $expected): void
    {
        self::assertSame($expected, SlugUtility::getLastSegment($slug));
    }
}
