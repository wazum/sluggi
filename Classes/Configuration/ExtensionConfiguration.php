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

    public function isSyncDefaultEnabled(): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get(
                extension: 'sluggi',
                path: 'synchronize_default'
            );
        } catch (Exception) {
            return true;
        }
    }

    /**
     * @return list<string>
     */
    public function getSynchronizeTables(): array
    {
        try {
            $value = (string)$this->extensionConfiguration->get(
                extension: 'sluggi',
                path: 'synchronize_tables'
            );
            if ($value === '') {
                return [];
            }

            return array_values(array_filter(array_map(trim(...), explode(',', $value))));
        } catch (Exception) {
            return [];
        }
    }

    public function isTableSynchronizeEnabled(string $table): bool
    {
        return in_array($table, $this->getSynchronizeTables(), true);
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

    public function isLockDescendantsEnabled(): bool
    {
        try {
            return $this->isLockEnabled() && (bool)$this->extensionConfiguration->get(
                extension: 'sluggi',
                path: 'lock_descendants'
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

    public function isFullPathEditingEnabled(): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get(
                extension: 'sluggi',
                path: 'allow_full_path_editing'
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

    public function isCopyUrlEnabled(): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get(
                extension: 'sluggi',
                path: 'copy_url'
            );
        } catch (Exception) {
            return false;
        }
    }

    public function isCollapsedControlsEnabled(): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get(
                extension: 'sluggi',
                path: 'collapsed_controls'
            );
        } catch (Exception) {
            return false;
        }
    }
}
