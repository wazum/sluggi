<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Slug;

use B13\Masi\SlugModifier as MasiSlugModifier;
use TYPO3\CMS\Backend\Utility\BackendUtility;

// Belt-and-suspenders alongside the conditional postModifier registration in
// Configuration/TCA/Overrides/pages.php: if anything autoloads this file in an
// install without masi (composer classmap-authoritative, IDE indexer, third-
// party static analysis over vendor/), the early return prevents a fatal
// "parent class not found" before the class declaration is reached.
if (!class_exists(MasiSlugModifier::class)) {
    return;
}

/**
 * Cooperates with b13/masi when both extensions are installed.
 *
 * masi rebuilds the slug from scratch in its postModifier, deciding which
 * ancestors contribute to the URL prefix in resolveParentPageRecord(). Out of
 * the box masi only skips Recyclers (doktype 255) and pages with the per-page
 * exclude_slug_for_subpages flag set. That ignores sluggi's exclude_doktypes
 * config (e.g. doktype 199 Spacer or 254 Sysfolder) and silently re-includes
 * those ancestors in the resulting URL.
 *
 * This subclass merges sluggi's exclude_doktypes into the same skip-loop, so
 * both signals (per-page flag + doktype list) are honored without changing or
 * forking masi's slug rebuild logic. masi's PageTSconfig overrides, prefix,
 * fieldSeparator, replacements and language overlay are all inherited as-is.
 *
 * The class file is only loaded by the autoloader when the postModifier is
 * invoked, which only happens when sluggi has registered it in TCA — and we
 * only register it when masi is loaded. So masi remains an optional
 * dependency and this file is never parsed in installs without masi.
 */
final class SluggiAwareSlugModifier extends MasiSlugModifier
{
    private const DOKTYPE_RECYCLER = 255;

    /**
     * @return array<string, mixed>
     */
    protected function resolveParentPageRecord(int $pid, int $languageId): array
    {
        $sluggiExcluded = $this->getSluggiExcludedDoktypes();
        $rootLine = BackendUtility::BEgetRootLine(
            $pid,
            '',
            true,
            ['nav_title', 'exclude_slug_for_subpages']
        );

        do {
            $parentPageRecord = array_shift($rootLine) ?? [];
            $parentPageRecord = $this->tryRecordOverlay($parentPageRecord, $languageId);
            $doktype = (int)($parentPageRecord['doktype'] ?? 0);
            $excludedByMasi = (bool)($parentPageRecord['exclude_slug_for_subpages'] ?? false);
            $excludedBySluggi = in_array($doktype, $sluggiExcluded, true);
        } while (
            !empty($rootLine)
            && ($doktype === self::DOKTYPE_RECYCLER || $excludedByMasi || $excludedBySluggi)
        );

        return $parentPageRecord;
    }

    /**
     * @return list<int>
     */
    private function getSluggiExcludedDoktypes(): array
    {
        $value = (string)($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] ?? '');
        if ($value === '') {
            return [];
        }

        return array_values(array_map(intval(...), array_filter(explode(',', $value))));
    }
}
