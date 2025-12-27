<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Rendering;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;
use Wazum\Sluggi\Service\SlugSourceBadgeRenderer;

final class SlugSourceBadgeRendererTest extends TestCase
{
    private SlugSourceBadgeRenderer $subject;
    private string $elementClass;

    protected function setUp(): void
    {
        $this->subject = new SlugSourceBadgeRenderer();
        $this->elementClass = Typo3Compatibility::getFormWizardsElementClass();
    }

    public static function badgeWithMetadataProvider(): Generator
    {
        yield 'single field shows just slot number' => [
            'metadata' => ['slot' => 1, 'role' => 'single', 'chainSize' => 1],
            'expectedContent' => 'sluggi-source-badge__number">1</span>',
            'expectedTitle' => 'This field influences the URL slug (priority 1)',
        ];

        yield 'preferred field shows slot with preferred indicator' => [
            'metadata' => ['slot' => 1, 'role' => 'preferred', 'chainSize' => 2],
            'expectedContent' => 'sluggi-source-badge__number">1</span>',
            'expectedTitle' => 'This field influences the URL slug (priority 1, used if filled)',
        ];

        yield 'fallback field shows slot with fallback indicator' => [
            'metadata' => ['slot' => 1, 'role' => 'fallback', 'chainSize' => 2],
            'expectedContent' => 'sluggi-source-badge__number">1</span>',
            'expectedTitle' => 'This field influences the URL slug (priority 1, fallback)',
        ];

        yield 'second slot preferred field' => [
            'metadata' => ['slot' => 2, 'role' => 'preferred', 'chainSize' => 2],
            'expectedContent' => 'sluggi-source-badge__number">2</span>',
            'expectedTitle' => 'This field influences the URL slug (priority 2, used if filled)',
        ];
    }

    /**
     * @param array{slot: int, role: string, chainSize: int} $metadata
     */
    #[Test]
    #[DataProvider('badgeWithMetadataProvider')]
    public function renderBadgeWithMetadataShowsCorrectContent(
        array $metadata,
        string $expectedContent,
        string $expectedTitle,
    ): void {
        $badge = $this->subject->renderBadgeWithMetadata($metadata, totalFields: 2);

        self::assertStringContainsString($expectedContent, $badge);
        self::assertStringContainsString('title="' . $expectedTitle . '"', $badge);
    }

    #[Test]
    public function renderBadgeWithMetadataContainsSvgIcon(): void
    {
        $metadata = ['slot' => 1, 'role' => 'single', 'chainSize' => 1];

        $badge = $this->subject->renderBadgeWithMetadata($metadata, totalFields: 1);

        self::assertStringContainsString('<svg', $badge);
        self::assertStringContainsString('</svg>', $badge);
    }

    #[Test]
    public function renderBadgeWithMetadataContainsCssClass(): void
    {
        $metadata = ['slot' => 1, 'role' => 'single', 'chainSize' => 1];

        $badge = $this->subject->renderBadgeWithMetadata($metadata, totalFields: 1);

        self::assertStringContainsString('class="input-group-text sluggi-source-badge"', $badge);
    }

    #[Test]
    public function renderBadgeWithMetadataContainsFallbackCssClassForFallbackRole(): void
    {
        $metadata = ['slot' => 1, 'role' => 'fallback', 'chainSize' => 2];

        $badge = $this->subject->renderBadgeWithMetadata($metadata, totalFields: 2);

        self::assertStringContainsString('sluggi-source-badge--fallback', $badge);
    }

    #[Test]
    public function renderBadgeWithMetadataOmitsNumberWhenOnlyOneField(): void
    {
        $metadata = ['slot' => 1, 'role' => 'single', 'chainSize' => 1];

        $badge = $this->subject->renderBadgeWithMetadata($metadata, totalFields: 1);

        self::assertStringNotContainsString('sluggi-source-badge__number', $badge);
    }

    #[Test]
    public function renderBadgeWithMetadataShowsNumberWhenMultipleFields(): void
    {
        $metadata = ['slot' => 1, 'role' => 'single', 'chainSize' => 1];

        $badge = $this->subject->renderBadgeWithMetadata($metadata, totalFields: 2);

        self::assertStringContainsString('sluggi-source-badge__number', $badge);
    }

    #[Test]
    public function renderBadgeWithMetadataDoesNotContainFallbackCssClassForSingleRole(): void
    {
        $metadata = ['slot' => 1, 'role' => 'single', 'chainSize' => 1];

        $badge = $this->subject->renderBadgeWithMetadata($metadata, totalFields: 1);

        self::assertStringNotContainsString('sluggi-source-badge--fallback', $badge);
    }

    #[Test]
    public function insertBadgeIntoHtmlWrapsInputInGroupWhenNotHidden(): void
    {
        $html = '<div class="' . $this->elementClass . '"><input type="text" name="test"></div>';

        $result = $this->subject->insertBadgeIntoHtml($html, '<span class="badge">1</span>', hidden: false);

        self::assertStringContainsString('<div class="input-group">', $result);
        self::assertStringContainsString('<span class="badge">1</span><input', $result);
    }

    #[Test]
    public function insertBadgeIntoHtmlOmitsInputGroupClassWhenHidden(): void
    {
        $html = '<div class="' . $this->elementClass . '"><input type="text" name="test"></div>';

        $result = $this->subject->insertBadgeIntoHtml($html, '<span class="badge">1</span>', hidden: true);

        self::assertStringNotContainsString('class="input-group"', $result);
        self::assertStringContainsString('<span class="badge">1</span><input', $result);
    }

    #[Test]
    public function insertBadgeDoesNotAddDataSluggiSourceAttribute(): void
    {
        $html = '<div class="' . $this->elementClass . '"><input type="text" name="test"></div>';

        $result = $this->subject->insertBadgeIntoHtml($html, '<span>badge</span>');

        self::assertStringNotContainsString('data-sluggi-source', $result);
    }

    #[Test]
    public function markAsSourceFieldAddsDataSluggiSourceAttribute(): void
    {
        $html = '<div class="' . $this->elementClass . '"><input type="text" name="test"></div>';

        $result = $this->subject->markAsSourceField($html);

        self::assertStringContainsString('data-sluggi-source', $result);
    }

    #[Test]
    public function markAsSourceFieldPreservesExistingInputAttributes(): void
    {
        $html = '<div class="' . $this->elementClass . '"><input type="text" name="data[pages][123][title]" class="form-control"></div>';

        $result = $this->subject->markAsSourceField($html);

        self::assertStringContainsString('name="data[pages][123][title]"', $result);
        self::assertStringContainsString('class="form-control"', $result);
        self::assertStringContainsString('data-sluggi-source', $result);
    }

    #[Test]
    public function insertBadgePreservesExistingInputAttributes(): void
    {
        $html = '<div class="' . $this->elementClass . '"><input type="text" name="data[pages][123][title]" class="form-control"></div>';

        $result = $this->subject->insertBadgeIntoHtml($html, '<span>badge</span>');

        self::assertStringContainsString('name="data[pages][123][title]"', $result);
        self::assertStringContainsString('class="form-control"', $result);
    }

    #[Test]
    public function markAsSourceFieldOnlyDoesNotAddBadge(): void
    {
        $html = '<div class="' . $this->elementClass . '"><input type="text" name="test"></div>';

        $result = $this->subject->markAsSourceField($html);

        self::assertStringContainsString('data-sluggi-source', $result);
        self::assertStringNotContainsString('sluggi-source-badge', $result);
        self::assertStringNotContainsString('input-group', $result);
    }

    #[Test]
    public function insertBadgeWorksAfterMarkAsSourceField(): void
    {
        $html = '<div class="' . $this->elementClass . '"><input type="text" name="test"></div>';
        $badge = '<span class="sluggi-source-badge">badge</span>';

        $markedHtml = $this->subject->markAsSourceField($html);
        $result = $this->subject->insertBadgeIntoHtml($markedHtml, $badge);

        self::assertStringContainsString('data-sluggi-source', $result);
        self::assertStringContainsString('sluggi-source-badge', $result);
        self::assertStringContainsString('input-group', $result);
    }

    #[Test]
    public function renderBadgeWithMetadataDoesNotIncludeDisplayNoneByDefault(): void
    {
        $metadata = ['slot' => 1, 'role' => 'single', 'chainSize' => 1];

        $badge = $this->subject->renderBadgeWithMetadata($metadata, totalFields: 1);

        self::assertStringNotContainsString('style="display:none"', $badge);
    }

    #[Test]
    public function renderBadgeWithMetadataIncludesDisplayNoneWhenHidden(): void
    {
        $metadata = ['slot' => 1, 'role' => 'single', 'chainSize' => 1];

        $badge = $this->subject->renderBadgeWithMetadata($metadata, totalFields: 1, hidden: true);

        self::assertStringContainsString('style="display:none"', $badge);
    }

    #[Test]
    public function renderBadgeWithMetadataDoesNotIncludeDisplayNoneWhenNotHidden(): void
    {
        $metadata = ['slot' => 1, 'role' => 'single', 'chainSize' => 1];

        $badge = $this->subject->renderBadgeWithMetadata($metadata, totalFields: 1, hidden: false);

        self::assertStringNotContainsString('style="display:none"', $badge);
    }
}
