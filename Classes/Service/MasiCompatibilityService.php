<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

final readonly class MasiCompatibilityService
{
    private const FIELD_NAME = 'exclude_slug_for_subpages';

    /**
     * Doktypes whose URL-inclusion behavior masi changes vs. TYPO3 core:
     * core skips Spacer (199) and Sysfolder (254) in subpage slug paths;
     * masi re-includes them by default ("include by default, opt-out per page").
     * When masi is installed it explicitly opts back into having these in URLs,
     * so sluggi defers to masi for these values and drops them from
     * exclude_doktypes at boot.
     *
     * @var list<int>
     */
    public const MANAGED_DOKTYPES = [199, 254];

    /**
     * @param list<int> $doktypes
     *
     * @return list<int>
     */
    public static function removeManagedDoktypes(array $doktypes): array
    {
        return array_values(array_diff($doktypes, self::MANAGED_DOKTYPES));
    }

    public function isActive(): bool
    {
        return ExtensionManagementUtility::isLoaded('masi');
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function isExclusionFieldSubmitted(array $fieldArray): bool
    {
        return array_key_exists(self::FIELD_NAME, $fieldArray);
    }

    public function getCurrentExclusionValue(int $pageId): bool
    {
        $record = BackendUtility::getRecordWSOL('pages', $pageId, self::FIELD_NAME);

        return (bool)($record[self::FIELD_NAME] ?? false);
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function getSubmittedExclusionValue(array $fieldArray): bool
    {
        return (bool)($fieldArray[self::FIELD_NAME] ?? false);
    }
}
