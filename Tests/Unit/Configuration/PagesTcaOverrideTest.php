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
    public function pagesTcaOverrideLeavesGeneratorFieldRenderersUntouched(): void
    {
        $GLOBALS['TCA']['pages']['columns'] = [
            'title' => [
                'config' => [
                    'type' => 'text',
                    'enableRichtext' => true,
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

        self::assertArrayNotHasKey(
            'renderType',
            $GLOBALS['TCA']['pages']['columns']['nav_title']['config'],
            'A source field must keep whatever renderer its own TCA asks for.'
        );
        self::assertArrayNotHasKey(
            'renderType',
            $GLOBALS['TCA']['pages']['columns']['title']['config'],
            'A richtext source field must keep its editor instead of becoming a text input.'
        );
    }
}
