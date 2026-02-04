<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Sluggi\Utility\DataHandlerUtility;

final class DataHandlerUtilityTest extends TestCase
{
    /**
     * @return array<string, array{id: string|int, expected: bool}>
     */
    public static function isNewRecordDataProvider(): array
    {
        return [
            'NEW prefix string' => [
                'id' => 'NEW6789',
                'expected' => true,
            ],
            'NEW with hash' => [
                'id' => 'NEW64a1b2c3d4e5f',
                'expected' => true,
            ],
            'integer id' => [
                'id' => 42,
                'expected' => false,
            ],
            'numeric string' => [
                'id' => '42',
                'expected' => false,
            ],
            'zero integer' => [
                'id' => 0,
                'expected' => false,
            ],
            'non-numeric non-NEW string' => [
                'id' => 'abc',
                'expected' => true,
            ],
        ];
    }

    #[Test]
    #[DataProvider('isNewRecordDataProvider')]
    public function isNewRecordReturnsExpectedResult(string|int $id, bool $expected): void
    {
        self::assertSame($expected, DataHandlerUtility::isNewRecord($id));
    }
}
