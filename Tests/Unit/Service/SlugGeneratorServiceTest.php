<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Sluggi\Service\SlugGeneratorService;

final class SlugGeneratorServiceTest extends TestCase
{
    private SlugGeneratorService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new SlugGeneratorService();
    }

    /** @return array<string, array{slug: string, expected: string}> */
    public static function lastSegmentDataProvider(): array
    {
        return [
            'multi-segment slug' => [
                'slug' => '/parent/child',
                'expected' => '/child',
            ],
            'single segment' => [
                'slug' => '/page',
                'expected' => '/page',
            ],
            'empty string' => [
                'slug' => '',
                'expected' => '/',
            ],
            'trailing slash' => [
                'slug' => '/parent/child/',
                'expected' => '/child',
            ],
        ];
    }

    #[Test]
    #[DataProvider('lastSegmentDataProvider')]
    public function getLastSegmentReturnsExpectedResult(string $slug, string $expected): void
    {
        self::assertSame($expected, $this->subject->getLastSegment($slug));
    }

    /** @return array<string, array{parentSlug: string, childSlug: string, expected: string}> */
    public static function slugCombinationDataProvider(): array
    {
        return [
            'simple case' => [
                'parentSlug' => '/parent-page',
                'childSlug' => '/child-page',
                'expected' => '/parent-page/child-page',
            ],
            'child already has parent prefix' => [
                'parentSlug' => '/parent-page',
                'childSlug' => '/old-parent/child-page',
                'expected' => '/parent-page/child-page',
            ],
            'root parent empty string' => [
                'parentSlug' => '',
                'childSlug' => '/child',
                'expected' => '/child',
            ],
            'trailing slash in parent' => [
                'parentSlug' => '/parent/',
                'childSlug' => '/child',
                'expected' => '/parent/child',
            ],
        ];
    }

    #[Test]
    #[DataProvider('slugCombinationDataProvider')]
    public function combineWithParentProducesExpectedResults(
        string $parentSlug,
        string $childSlug,
        string $expected,
    ): void {
        self::assertSame($expected, $this->subject->combineWithParent($parentSlug, $childSlug));
    }
}
