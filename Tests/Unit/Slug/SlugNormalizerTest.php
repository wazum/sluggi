<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Slug;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use Wazum\Sluggi\Slug\SlugNormalizer;

final class SlugNormalizerTest extends TestCase
{
    private function createSlugNormalizer(bool $preserveUnderscore = false): SlugNormalizer
    {
        $charsetConverter = $this->createMock(CharsetConverter::class);
        $charsetConverter->method('utf8_char_mapping')
            ->willReturnCallback(static fn (string $value): string => $value);

        return new SlugNormalizer($charsetConverter, $preserveUnderscore);
    }

    /**
     * @return array<string, array{input: string, expected: string, fallback: string}>
     */
    public static function normalizeDataProvider(): array
    {
        return [
            'simple lowercase' => [
                'input' => 'Hello World',
                'expected' => 'hello-world',
                'fallback' => '-',
            ],
            'underscores replaced by default' => [
                'input' => 'hello_world',
                'expected' => 'hello-world',
                'fallback' => '-',
            ],
            'multiple spaces collapsed' => [
                'input' => 'hello   world',
                'expected' => 'hello-world',
                'fallback' => '-',
            ],
            'tabs replaced' => [
                'input' => "hello\tworld",
                'expected' => 'hello-world',
                'fallback' => '-',
            ],
            'non-breaking space replaced' => [
                'input' => "hello\u{00A0}world",
                'expected' => 'hello-world',
                'fallback' => '-',
            ],
            'plus sign replaced' => [
                'input' => 'hello+world',
                'expected' => 'hello-world',
                'fallback' => '-',
            ],
            'dash normalized' => [
                'input' => 'hello--world',
                'expected' => 'hello-world',
                'fallback' => '-',
            ],
            'special characters removed' => [
                'input' => 'hello@world!',
                'expected' => 'helloworld',
                'fallback' => '-',
            ],
            'slashes preserved' => [
                'input' => 'hello/world',
                'expected' => 'hello/world',
                'fallback' => '-',
            ],
            'leading trailing fallback trimmed' => [
                'input' => '-hello-world-',
                'expected' => 'hello-world',
                'fallback' => '-',
            ],
            'numbers preserved' => [
                'input' => 'page123',
                'expected' => 'page123',
                'fallback' => '-',
            ],
            'custom fallback character underscore' => [
                'input' => 'hello world',
                'expected' => 'hello_world',
                'fallback' => '_',
            ],
        ];
    }

    #[Test]
    #[DataProvider('normalizeDataProvider')]
    public function normalizeReturnsExpectedResult(string $input, string $expected, string $fallback): void
    {
        $subject = $this->createSlugNormalizer();

        $result = $subject->normalize($input, $fallback);

        self::assertSame($expected, $result);
    }

    #[Test]
    public function normalizeUsesDefaultFallbackWhenNull(): void
    {
        $subject = $this->createSlugNormalizer();

        $result = $subject->normalize('hello world', null);

        self::assertSame('hello-world', $result);
    }

    #[Test]
    public function underscoresPreservedWhenOptionEnabled(): void
    {
        $subject = $this->createSlugNormalizer(preserveUnderscore: true);

        $result = $subject->normalize('hello_world', '-');

        self::assertSame('hello_world', $result);
    }

    #[Test]
    public function underscoresReplacedWhenOptionDisabled(): void
    {
        $subject = $this->createSlugNormalizer(preserveUnderscore: false);

        $result = $subject->normalize('hello_world', '-');

        self::assertSame('hello-world', $result);
    }

    #[Test]
    public function multipleUnderscoresPreservedWhenOptionEnabled(): void
    {
        $subject = $this->createSlugNormalizer(preserveUnderscore: true);

        $result = $subject->normalize('my_test_page', '-');

        self::assertSame('my_test_page', $result);
    }

    #[Test]
    public function mixedUnderscoresAndSpacesNormalizedCorrectly(): void
    {
        $subject = $this->createSlugNormalizer(preserveUnderscore: true);

        $result = $subject->normalize('hello_world test', '-');

        self::assertSame('hello_world-test', $result);
    }
}
