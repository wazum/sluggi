<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Wazum\Sluggi\Service\SlugElementRenderer;

final class SlugElementRendererTest extends TestCase
{
    public function testMarksTheElementForTheLegacySaveClick(): void
    {
        $attributes = (new SlugElementRenderer())->buildAttributes(
            [...$this->context(), 'legacySave' => true],
            [],
        );

        self::assertArrayHasKey('legacy-save', $attributes);
    }

    public function testLeavesTheLegacySaveMarkerOutByDefault(): void
    {
        $attributes = (new SlugElementRenderer())->buildAttributes($this->context(), []);

        self::assertArrayNotHasKey('legacy-save', $attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            'decodedValue' => '/page',
            'effectivePid' => 1,
            'recordId' => 1,
            'table' => 'pages',
            'fieldName' => 'slug',
            'languageId' => 0,
            'signature' => 'signature',
            'command' => 'edit',
            'parentPageId' => 0,
            'fallbackCharacter' => '-',
            'includeUid' => false,
            'hasPostModifiers' => false,
            'requiredSourceFields' => [],
            'sourceFields' => [],
            'isSynced' => false,
        ];
    }
}
