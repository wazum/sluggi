<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Slug;

use Exception;
use Normalizer;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as CoreExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[Autoconfigure(public: true)]
final readonly class SlugNormalizer
{
    public function __construct(
        private CharsetConverter $charsetConverter,
        private ?bool $preserveUnderscoreOverride = null,
    ) {
    }

    public function normalize(string $value, ?string $fallbackCharacter = '-'): string
    {
        $fallbackCharacter ??= '-';
        $preserveUnderscore = $this->isPreserveUnderscoreEnabled();

        $value = mb_strtolower($value, 'utf-8');
        $value = strip_tags($value);

        if ($preserveUnderscore) {
            $value = (string)preg_replace('/[ \t\x{00A0}\-+]+/u', $fallbackCharacter, $value);
        } else {
            $value = (string)preg_replace('/[ \t\x{00A0}\-+_]+/u', $fallbackCharacter, $value);
        }

        if (!Normalizer::isNormalized($value)) {
            $value = Normalizer::normalize($value) ?: $value;
        }

        $value = $this->charsetConverter->utf8_char_mapping($value);

        $allowedChars = preg_quote($fallbackCharacter);
        if ($preserveUnderscore) {
            $allowedChars .= '_';
        }
        $value = (string)preg_replace('/[^\p{L}\p{M}0-9\/' . $allowedChars . ']/u', '', $value);

        if ($fallbackCharacter !== '') {
            $value = (string)preg_replace('/' . preg_quote($fallbackCharacter, '/') . '{2,}/', $fallbackCharacter, $value);
        }

        $value = mb_strtolower($value, 'utf-8');
        $extractedSlug = trim($value, $fallbackCharacter . '/');

        $appendTrailingSlash = $extractedSlug !== '' && substr($value, -1) === '/';
        $value = $extractedSlug . ($appendTrailingSlash ? '/' : '');

        return $value;
    }

    private function isPreserveUnderscoreEnabled(): bool
    {
        if ($this->preserveUnderscoreOverride !== null) {
            return $this->preserveUnderscoreOverride;
        }

        try {
            return (bool)GeneralUtility::makeInstance(CoreExtensionConfiguration::class)
                ->get('sluggi', 'preserve_underscore');
        } catch (Exception) {
            return false;
        }
    }
}
