<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Redirects\Service\SlugService;
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

    #[Test]
    public function isNestedSlugUpdateReturnsFalseWhenCorrelationIdIsNull(): void
    {
        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->method('getCorrelationId')->willReturn(null);

        self::assertFalse(DataHandlerUtility::isNestedSlugUpdate($dataHandler));
    }

    #[Test]
    public function isNestedSlugUpdateReturnsFalseWhenSlugServiceIdentifierIsMissing(): void
    {
        $correlationId = CorrelationId::forScope('test')->withAspects('unrelated');
        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->method('getCorrelationId')->willReturn($correlationId);

        self::assertFalse(DataHandlerUtility::isNestedSlugUpdate($dataHandler));
    }

    #[Test]
    public function isNestedSlugUpdateReturnsTrueOnSlugUpdateCascade(): void
    {
        // Matches SlugService::$correlationIdSlugUpdate construction (CORRELATION_ID_IDENTIFIER, 'slug').
        $correlationId = CorrelationId::forScope('test')->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'slug');
        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->method('getCorrelationId')->willReturn($correlationId);

        self::assertTrue(DataHandlerUtility::isNestedSlugUpdate($dataHandler));
    }

    #[Test]
    public function isNestedSlugUpdateReturnsFalseOnRedirectCreationFlow(): void
    {
        // Matches SlugService::$correlationIdRedirectCreation construction (CORRELATION_ID_IDENTIFIER, 'redirect').
        // Our hooks must still run for redirect-creation flows; only slug cascades should short-circuit.
        $correlationId = CorrelationId::forScope('test')->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'redirect');
        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->method('getCorrelationId')->willReturn($correlationId);

        self::assertFalse(DataHandlerUtility::isNestedSlugUpdate($dataHandler));
    }
}
