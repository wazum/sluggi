<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Configuration;

use Exception;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as CoreExtensionConfiguration;

final readonly class ExtensionConfiguration
{
    public function __construct(
        private CoreExtensionConfiguration $extensionConfiguration,
    ) {
    }

    public function isSyncEnabled(): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get(
                extension: 'sluggi',
                path: 'synchronize'
            );
        } catch (Exception) {
            return false;
        }
    }

    public function isLockEnabled(): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get(
                extension: 'sluggi',
                path: 'lock'
            );
        } catch (Exception) {
            return false;
        }
    }

    public function isLastSegmentOnlyEnabled(): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get(
                extension: 'sluggi',
                path: 'last_segment_only'
            );
        } catch (Exception) {
            return false;
        }
    }

    /**
     * @return list<int>
     */
    public function getExcludedPageTypes(): array
    {
        try {
            $value = (string)$this->extensionConfiguration->get(
                extension: 'sluggi',
                path: 'exclude_doktypes'
            );
            if ($value === '') {
                return [];
            }

            return array_values(array_map(intval(...), array_filter(explode(',', $value))));
        } catch (Exception) {
            return [];
        }
    }

    public function isPageTypeExcluded(int $pageType): bool
    {
        return in_array($pageType, $this->getExcludedPageTypes(), true);
    }
}
