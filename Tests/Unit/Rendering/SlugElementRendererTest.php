<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Rendering;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Sluggi\Service\SlugElementRenderer;

final class SlugElementRendererTest extends TestCase
{
    public static function attributeMappingDataProvider(): Generator
    {
        yield 'language from languageId' => ['languageId', 3, 'language', '3'];
        yield 'signature' => ['signature', 'hmac_sig', 'signature', 'hmac_sig'];
        yield 'command' => ['command', 'new', 'command', 'new'];
        yield 'parent-page-id from parentPageId' => ['parentPageId', 42, 'parent-page-id', '42'];
        yield 'fallback-character' => ['fallbackCharacter', '-', 'fallback-character', '-'];
    }

    #[Test]
    public function buildAttributesReturnsRequiredAttributes(): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext([
            'decodedValue' => '/test-page',
            'effectivePid' => 1,
            'recordId' => 2,
            'table' => 'pages',
            'fieldName' => 'slug',
        ]);

        $result = $subject->buildAttributes($context, []);

        self::assertSame('/test-page', $result['value']);
        self::assertSame('1', $result['page-id']);
        self::assertSame('2', $result['record-id']);
        self::assertSame('pages', $result['table-name']);
        self::assertSame('slug', $result['field-name']);
    }

    #[Test]
    #[DataProvider('attributeMappingDataProvider')]
    public function buildAttributesMapsContextToAttribute(string $contextKey, mixed $contextValue, string $attributeKey, string $expectedValue): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext([$contextKey => $contextValue]);

        $result = $subject->buildAttributes($context, []);

        self::assertSame($expectedValue, $result[$attributeKey]);
    }

    #[Test]
    public function buildAttributesEncodesLabelsAsJson(): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext();
        $labels = ['conflict.title' => 'Slug Conflict', 'conflict.message' => 'Exists'];

        $result = $subject->buildAttributes($context, $labels);

        self::assertSame('{"conflict.title":"Slug Conflict","conflict.message":"Exists"}', $result['labels']);
    }

    #[Test]
    public function buildAttributesIncludesIncludeUidWhenTrue(): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext(['includeUid' => true]);

        $result = $subject->buildAttributes($context, []);

        self::assertArrayHasKey('include-uid', $result);
    }

    #[Test]
    public function buildAttributesIncludesHasPostModifiersWhenTrue(): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext(['hasPostModifiers' => true]);

        $result = $subject->buildAttributes($context, []);

        self::assertArrayHasKey('has-post-modifiers', $result);
    }

    #[Test]
    public function buildAttributesIncludesIsSyncedWhenTrue(): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext(['isSynced' => true]);

        $result = $subject->buildAttributes($context, []);

        self::assertArrayHasKey('is-synced', $result);
    }

    #[Test]
    public function buildAttributesIncludesSyncFeatureEnabledWhenTrue(): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext(['syncFeatureEnabled' => true]);

        $result = $subject->buildAttributes($context, []);

        self::assertArrayHasKey('sync-feature-enabled', $result);
    }

    #[Test]
    public function buildAttributesOmitsSyncFeatureEnabledWhenFalse(): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext(['syncFeatureEnabled' => false]);

        $result = $subject->buildAttributes($context, []);

        self::assertArrayNotHasKey('sync-feature-enabled', $result);
    }

    #[Test]
    public function buildAttributesIncludesLastSegmentOnlyWhenTrue(): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext(['lastSegmentOnly' => true]);

        $result = $subject->buildAttributes($context, []);

        self::assertArrayHasKey('last-segment-only', $result);
    }

    #[Test]
    public function buildAttributesOmitsLastSegmentOnlyWhenFalse(): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext(['lastSegmentOnly' => false]);

        $result = $subject->buildAttributes($context, []);

        self::assertArrayNotHasKey('last-segment-only', $result);
    }

    #[Test]
    public function buildAttributeStringIncludesBooleanAttributesWithEmptyValues(): void
    {
        $subject = new SlugElementRenderer();
        $attributes = [
            'value' => '/test',
            'sync-feature-enabled' => '',
            'is-synced' => '',
        ];

        $result = $subject->buildAttributeString($attributes);

        self::assertStringContainsString('value="/test"', $result);
        self::assertStringContainsString('sync-feature-enabled', $result);
        self::assertStringContainsString('is-synced', $result);
        self::assertStringNotContainsString('sync-feature-enabled=""', $result);
    }

    #[Test]
    public function buildAttributeStringOmitsAttributesNotInArray(): void
    {
        $subject = new SlugElementRenderer();
        $attributes = ['value' => '/test'];

        $result = $subject->buildAttributeString($attributes);

        self::assertStringContainsString('value="/test"', $result);
        self::assertStringNotContainsString('sync-feature-enabled', $result);
    }

    #[Test]
    public function buildAttributeStringEscapesHtmlSpecialChars(): void
    {
        $subject = new SlugElementRenderer();
        $attributes = ['value' => '/test<script>'];

        $result = $subject->buildAttributeString($attributes);

        self::assertStringContainsString('value="/test&lt;script&gt;"', $result);
    }

    #[Test]
    public function buildSyncFieldNameWithIntegerRecordId(): void
    {
        $subject = new SlugElementRenderer();

        $result = $subject->buildSyncFieldName('pages', 123);

        self::assertSame('data[pages][123][tx_sluggi_sync]', $result);
    }

    #[Test]
    public function buildSyncFieldNameWithNewRecordId(): void
    {
        $subject = new SlugElementRenderer();

        $result = $subject->buildSyncFieldName('pages', 'NEW6947faed9ea6d547219265');

        self::assertSame('data[pages][NEW6947faed9ea6d547219265][tx_sluggi_sync]', $result);
    }

    #[Test]
    public function buildAttributesIncludesCopyUrlFeatureEnabledWhenTrue(): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext([
            'copyUrlFeatureEnabled' => true,
            'pageUrl' => 'https://example.com',
        ]);

        $result = $subject->buildAttributes($context, []);

        self::assertArrayHasKey('copy-url-feature-enabled', $result);
        self::assertSame('https://example.com', $result['page-url']);
    }

    #[Test]
    public function buildAttributesOmitsCopyUrlFeatureEnabledWhenFalse(): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext(['copyUrlFeatureEnabled' => false]);

        $result = $subject->buildAttributes($context, []);

        self::assertArrayNotHasKey('copy-url-feature-enabled', $result);
        self::assertArrayNotHasKey('page-url', $result);
    }

    #[Test]
    public function buildAttributesIncludesCollapsedControlsWhenTrue(): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext(['collapsedControlsEnabled' => true]);

        $result = $subject->buildAttributes($context, []);

        self::assertArrayHasKey('collapsed-controls', $result);
    }

    #[Test]
    public function buildAttributesOmitsCollapsedControlsWhenFalse(): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext(['collapsedControlsEnabled' => false]);

        $result = $subject->buildAttributes($context, []);

        self::assertArrayNotHasKey('collapsed-controls', $result);
    }

    #[Test]
    public function buildRedirectFieldNameWithIntegerRecordId(): void
    {
        $subject = new SlugElementRenderer();

        $result = $subject->buildRedirectFieldName('pages', 123);

        self::assertSame('data[pages][123][tx_sluggi_redirect]', $result);
    }

    #[Test]
    public function buildRedirectFieldNameWithNewRecordId(): void
    {
        $subject = new SlugElementRenderer();

        $result = $subject->buildRedirectFieldName('pages', 'NEW6947faed9ea6d547219265');

        self::assertSame('data[pages][NEW6947faed9ea6d547219265][tx_sluggi_redirect]', $result);
    }

    #[Test]
    public function buildAttributesIncludesRedirectControlEnabledWhenTrue(): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext(['redirectControlEnabled' => true]);

        $result = $subject->buildAttributes($context, []);

        self::assertArrayHasKey('redirect-control', $result);
    }

    #[Test]
    public function buildAttributesOmitsRedirectControlEnabledWhenFalse(): void
    {
        $subject = new SlugElementRenderer();
        $context = $this->createContext(['redirectControlEnabled' => false]);

        $result = $subject->buildAttributes($context, []);

        self::assertArrayNotHasKey('redirect-control', $result);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function createContext(array $overrides = []): array
    {
        return array_merge([
            'decodedValue' => '/test-page',
            'effectivePid' => 1,
            'recordId' => 2,
            'table' => 'pages',
            'fieldName' => 'slug',
            'languageId' => 0,
            'signature' => 'abc123',
            'command' => 'edit',
            'parentPageId' => 1,
            'fallbackCharacter' => '-',
            'includeUid' => false,
            'hasPostModifiers' => false,
            'isLocked' => false,
            'syncFeatureEnabled' => false,
            'isSynced' => false,
            'lastSegmentOnly' => false,
            'redirectControlEnabled' => false,
        ], $overrides);
    }
}
