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

    /**
     * @return array<string, array{slug: string, expected: string}>
     */
    public static function parentPathDataProvider(): array
    {
        return [
            'multi-segment slug' => [
                'slug' => '/parent/child',
                'expected' => '/parent',
            ],
            'single segment' => [
                'slug' => '/page',
                'expected' => '',
            ],
            'empty string' => [
                'slug' => '',
                'expected' => '',
            ],
            'trailing slash' => [
                'slug' => '/parent/child/',
                'expected' => '/parent',
            ],
            'deeply nested' => [
                'slug' => '/a/b/c/d/e',
                'expected' => '/a/b/c/d',
            ],
            'root slash only' => [
                'slug' => '/',
                'expected' => '',
            ],
        ];
    }

    #[Test]
    #[DataProvider('parentPathDataProvider')]
    public function getParentPathReturnsExpectedResult(string $slug, string $expected): void
    {
        self::assertSame($expected, SlugUtility::getParentPath($slug));
    }

    /**
     * @return array<string, array{slug: string, parentSlug: string, expected: bool}>
     */
    public static function slugMatchesHierarchyDataProvider(): array
    {
        return [
            'slug matches parent hierarchy' => [
                'slug' => '/parent/child',
                'parentSlug' => '/parent',
                'expected' => true,
            ],
            'slug does not match parent hierarchy' => [
                'slug' => '/different/child',
                'parentSlug' => '/parent',
                'expected' => false,
            ],
            'empty parent slug' => [
                'slug' => '/any/path',
                'parentSlug' => '',
                'expected' => true,
            ],
            'root parent slug' => [
                'slug' => '/any/path',
                'parentSlug' => '/',
                'expected' => true,
            ],
            'parent slug with trailing slash' => [
                'slug' => '/parent/child',
                'parentSlug' => '/parent/',
                'expected' => true,
            ],
            'deeply nested matching hierarchy' => [
                'slug' => '/a/b/c/d',
                'parentSlug' => '/a/b/c',
                'expected' => true,
            ],
            'partial match is not a match' => [
                'slug' => '/parent-extended/child',
                'parentSlug' => '/parent',
                'expected' => false,
            ],
            'exact match without child segment' => [
                'slug' => '/parent',
                'parentSlug' => '/parent',
                'expected' => false,
            ],
        ];
    }

    #[Test]
    #[DataProvider('slugMatchesHierarchyDataProvider')]
    public function slugMatchesHierarchyReturnsExpectedResult(string $slug, string $parentSlug, bool $expected): void
    {
        self::assertSame($expected, SlugUtility::slugMatchesHierarchy($slug, $parentSlug));
    }
}
