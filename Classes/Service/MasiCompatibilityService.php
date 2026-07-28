<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

final readonly class MasiCompatibilityService
{
    private const FIELD_NAME = 'exclude_slug_for_subpages';

    public function isActive(): bool
    {
        return ExtensionManagementUtility::isLoaded('masi');
    }

    public function getExclusionFieldName(): string
    {
        return self::FIELD_NAME;
    }

    /**
     * @return list<string>
     */
    public function getAdditionalRootLineFields(): array
    {
        // BEgetRootLine() puts additional fields straight into its SELECT, and
        // the column only exists once masi is installed.
        return $this->isActive() ? [self::FIELD_NAME] : [];
    }

    /**
     * @param array<string, mixed> $page
     */
    public function excludesSlugForSubpages(array $page): bool
    {
        return (bool)($page[self::FIELD_NAME] ?? false);
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
