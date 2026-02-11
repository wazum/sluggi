<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Compatibility;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Centralized compatibility layer for TYPO3 12/13/14.
 * All version-specific values are defined here using match expressions.
 *
 * @deprecated Remove this entire file when dropping TYPO3 12 support
 */
final class Typo3Compatibility
{
    private static ?int $majorVersion = null;

    public static function getMajorVersion(): int
    {
        if (self::$majorVersion === null) {
            self::$majorVersion = (new Typo3Version())->getMajorVersion();
        }

        return self::$majorVersion;
    }

    /**
     * FormEngine form wizards element CSS class
     * TYPO3 12: 'form-wizards-element'
     * TYPO3 13+: 'form-wizards-item-element'.
     */
    public static function getFormWizardsElementClass(): string
    {
        return match (self::getMajorVersion()) {
            12 => 'form-wizards-element',
            default => 'form-wizards-item-element',
        };
    }

    /**
     * Legend CSS class for fieldset labels
     * TYPO3 12: 'form-legend'
     * TYPO3 13+: 'form-label'.
     */
    public static function getLegendClass(): string
    {
        return match (self::getMajorVersion()) {
            12 => 'form-legend t3js-formengine-legend',
            default => 'form-label t3js-formengine-label',
        };
    }

    /**
     * Check if TcaSchemaFactory exists (for tests)
     * TYPO3 12: false
     * TYPO3 13.0-13.1: false
     * TYPO3 13.2+: true.
     */
    public static function hasTcaSchemaFactory(): bool
    {
        return class_exists(\TYPO3\CMS\Core\Schema\TcaSchemaFactory::class);
    }

    /**
     * Generate HMAC hash compatible across TYPO3 versions
     * TYPO3 12: GeneralUtility::hmac()
     * TYPO3 13+: HashService->hmac().
     *
     * @param non-empty-string $additionalSecret
     *
     * @deprecated TYPO3 12 compatibility - use HashService directly when dropping v12
     */
    public static function hmac(string $input, string $additionalSecret): string
    {
        return match (self::getMajorVersion()) {
            // @phpstan-ignore staticMethod.notFound (method exists in TYPO3 12)
            12 => GeneralUtility::hmac($input, $additionalSecret),
            default => GeneralUtility::makeInstance(HashService::class)->hmac($input, $additionalSecret),
        };
    }

    /**
     * Check if the current request is editing multiple records.
     * On TYPO3 12, columnsOnly is not in the route's allowed redirect parameters,
     * so fieldListToRender stays empty. This fallback detects multi-edit by checking
     * for comma-separated UIDs in the edit parameter (which IS preserved).
     *
     * @deprecated Remove when dropping TYPO3 12 support — use fieldListToRender instead
     */
    public static function isMultiRecordEdit(): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return false;
        }

        foreach ($request->getQueryParams()['edit'] ?? [] as $uidConfig) {
            foreach (array_keys((array)$uidConfig) as $uidList) {
                if (str_contains((string)$uidList, ',')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Write site configuration compatible across TYPO3 versions
     * TYPO3 12: SiteConfiguration->write()
     * TYPO3 13+: SiteWriter->write().
     *
     * @param array<string, mixed> $configuration
     *
     * @deprecated TYPO3 12 compatibility - use SiteWriter directly when dropping v12
     */
    public static function writeSiteConfiguration(string $siteIdentifier, array $configuration): void
    {
        if (self::getMajorVersion() === 12) {
            // @phpstan-ignore method.notFound (method exists in TYPO3 12)
            GeneralUtility::makeInstance(SiteConfiguration::class)->write($siteIdentifier, $configuration);
        } else {
            GeneralUtility::makeInstance(SiteWriter::class)->write($siteIdentifier, $configuration);
        }
    }
}
