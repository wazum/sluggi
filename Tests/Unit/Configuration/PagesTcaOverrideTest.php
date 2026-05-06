<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PagesTcaOverrideTest extends TestCase
{
    private bool $hadOriginalTca = false;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $originalTca = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hadOriginalTca = array_key_exists('TCA', $GLOBALS);
        $this->originalTca = $GLOBALS['TCA'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->hadOriginalTca) {
            $GLOBALS['TCA'] = $this->originalTca;
        } else {
            unset($GLOBALS['TCA']);
        }

        parent::tearDown();
    }

    #[Test]
    public function pagesTcaOverrideMarksEveryFallbackGeneratorFieldAsSlugSource(): void
    {
        $GLOBALS['TCA']['pages']['columns'] = [
            'title' => [
                'config' => [
                    'type' => 'input',
                ],
            ],
            'nav_title' => [
                'config' => [
                    'type' => 'input',
                ],
            ],
            'slug' => [
                'config' => [
                    'type' => 'slug',
                    'generatorOptions' => [
                        'fields' => [
                            ['nav_title', 'title'],
                        ],
                    ],
                ],
            ],
        ];

        require __DIR__ . '/../../../Configuration/TCA/Overrides/pages.php';

        self::assertSame(
            'slugSourceInput',
            $GLOBALS['TCA']['pages']['columns']['nav_title']['config']['renderType'] ?? null,
            'The preferred fallback field must be sent to the slug AJAX generator.'
        );
        self::assertSame(
            'slugSourceInput',
            $GLOBALS['TCA']['pages']['columns']['title']['config']['renderType'] ?? null,
            'The final fallback field must still be sent to the slug AJAX generator.'
        );
    }
}
