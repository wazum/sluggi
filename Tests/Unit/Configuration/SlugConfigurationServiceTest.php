<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Sluggi\Service\SlugConfigurationService;

final class SlugConfigurationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TCA']);
    }

    /**
     * @return array<string, array{0: array<int, string|string[]>, 1: string[]}>
     */
    public static function fieldConfigurationProvider(): array
    {
        return [
            'single field (TYPO3 default for pages)' => [
                ['title'],
                ['title'],
            ],
            'simple string array' => [
                ['title', 'subtitle'],
                ['title', 'subtitle'],
            ],
            'single nested array with fallback fields' => [
                [['nav_title', 'title']],
                ['nav_title', 'title'],
            ],
            'multiple nested arrays' => [
                [['nav_title'], ['title']],
                ['nav_title', 'title'],
            ],
            'mixed nested and string format' => [
                [['nav_title', 'title'], 'subtitle'],
                ['nav_title', 'title', 'subtitle'],
            ],
            'multiple nested arrays with multiple fields' => [
                [['seo_title', 'title'], ['nav_title', 'subtitle']],
                ['seo_title', 'title', 'nav_title', 'subtitle'],
            ],
        ];
    }

    /**
     * @param array<int, string|string[]> $fieldsConfig
     * @param string[]                    $expected
     */
    #[Test]
    #[DataProvider('fieldConfigurationProvider')]
    public function getSourceFieldsHandlesVariousFieldConfigurations(array $fieldsConfig, array $expected): void
    {
        $GLOBALS['TCA']['pages']['columns']['slug']['config'] = [
            'type' => 'slug',
            'generatorOptions' => [
                'fields' => $fieldsConfig,
            ],
        ];

        $subject = new SlugConfigurationService();

        self::assertSame($expected, $subject->getSourceFields('pages'));
    }

    #[Test]
    public function getSourceFieldsReturnsEmptyArrayWhenNoSlugField(): void
    {
        $GLOBALS['TCA']['pages']['columns'] = [
            'title' => ['config' => ['type' => 'input']],
        ];

        $subject = new SlugConfigurationService();

        self::assertSame([], $subject->getSourceFields('pages'));
    }

    /**
     * @return array<string, array{0: array<int, string|string[]>, 1: array<string, array{slot: int, role: string, chainSize: int}>}>
     */
    public static function fieldMetadataProvider(): array
    {
        return [
            'simple string array - all single fields' => [
                ['title', 'subtitle'],
                [
                    'title' => ['slot' => 1, 'role' => 'single', 'chainSize' => 1],
                    'subtitle' => ['slot' => 2, 'role' => 'single', 'chainSize' => 1],
                ],
            ],
            'fallback chain with two fields' => [
                [['nav_title', 'title']],
                [
                    'nav_title' => ['slot' => 1, 'role' => 'preferred', 'chainSize' => 2],
                    'title' => ['slot' => 1, 'role' => 'fallback', 'chainSize' => 2],
                ],
            ],
            'fallback chain plus single field' => [
                [['nav_title', 'title'], 'subtitle'],
                [
                    'nav_title' => ['slot' => 1, 'role' => 'preferred', 'chainSize' => 2],
                    'title' => ['slot' => 1, 'role' => 'fallback', 'chainSize' => 2],
                    'subtitle' => ['slot' => 2, 'role' => 'single', 'chainSize' => 1],
                ],
            ],
            'two fallback chains' => [
                [['seo_title', 'title'], ['nav_title', 'subtitle']],
                [
                    'seo_title' => ['slot' => 1, 'role' => 'preferred', 'chainSize' => 2],
                    'title' => ['slot' => 1, 'role' => 'fallback', 'chainSize' => 2],
                    'nav_title' => ['slot' => 2, 'role' => 'preferred', 'chainSize' => 2],
                    'subtitle' => ['slot' => 2, 'role' => 'fallback', 'chainSize' => 2],
                ],
            ],
            'single element nested array is single role' => [
                [['title']],
                [
                    'title' => ['slot' => 1, 'role' => 'single', 'chainSize' => 1],
                ],
            ],
            'three field fallback chain' => [
                [['seo_title', 'nav_title', 'title']],
                [
                    'seo_title' => ['slot' => 1, 'role' => 'preferred', 'chainSize' => 3],
                    'nav_title' => ['slot' => 1, 'role' => 'fallback', 'chainSize' => 3],
                    'title' => ['slot' => 1, 'role' => 'fallback', 'chainSize' => 3],
                ],
            ],
        ];
    }

    /**
     * @param array<int, string|string[]>                                   $fieldsConfig
     * @param array<string, array{slot: int, role: string, chainSize: int}> $expected
     */
    #[Test]
    #[DataProvider('fieldMetadataProvider')]
    public function getFieldMetadataReturnsCorrectRolesAndSlots(array $fieldsConfig, array $expected): void
    {
        $GLOBALS['TCA']['pages']['columns']['slug']['config'] = [
            'type' => 'slug',
            'generatorOptions' => [
                'fields' => $fieldsConfig,
            ],
        ];

        $subject = new SlugConfigurationService();

        self::assertSame($expected, $subject->getFieldMetadata('pages'));
    }

    #[Test]
    public function getFieldMetadataReturnsEmptyArrayWhenNoSlugField(): void
    {
        $GLOBALS['TCA']['pages']['columns'] = [
            'title' => ['config' => ['type' => 'input']],
        ];

        $subject = new SlugConfigurationService();

        self::assertSame([], $subject->getFieldMetadata('pages'));
    }

    /**
     * @return array<string, array{0: array<int, string|string[]>, 1: string[]}>
     */
    public static function requiredFieldConfigurationProvider(): array
    {
        return [
            'single field (TYPO3 default for pages)' => [
                ['title'],
                ['title'],
            ],
            'two standalone fields' => [
                ['title', 'subtitle'],
                ['title', 'subtitle'],
            ],
            'fallback chain - only last field matters' => [
                [['nav_title', 'title']],
                ['title'],
            ],
            'fallback chain plus standalone' => [
                [['nav_title', 'title'], 'subtitle'],
                ['title', 'subtitle'],
            ],
            'multiple fallback chains' => [
                [['seo_title', 'title'], ['nav_title', 'subtitle']],
                ['title', 'subtitle'],
            ],
        ];
    }

    /**
     * @param array<int, string|string[]> $fieldsConfig
     * @param string[]                    $expected
     */
    #[Test]
    #[DataProvider('requiredFieldConfigurationProvider')]
    public function getRequiredSourceFieldsReturnsFieldsThatActuallyImpactSlug(array $fieldsConfig, array $expected): void
    {
        $GLOBALS['TCA']['pages']['columns']['slug']['config'] = [
            'type' => 'slug',
            'generatorOptions' => [
                'fields' => $fieldsConfig,
            ],
        ];

        $subject = new SlugConfigurationService();

        self::assertSame($expected, $subject->getRequiredSourceFields('pages'));
    }

    #[Test]
    public function getRequiredSourceFieldsReturnsEmptyArrayWhenNoSlugField(): void
    {
        $GLOBALS['TCA']['pages']['columns'] = [
            'title' => ['config' => ['type' => 'input']],
        ];

        $subject = new SlugConfigurationService();

        self::assertSame([], $subject->getRequiredSourceFields('pages'));
    }
}
