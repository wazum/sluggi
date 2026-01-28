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
        return $this->getBool('synchronize');
    }

    public function isSyncDefaultEnabled(): bool
    {
        return $this->getBool('synchronize_default', true);
    }

    /**
     * @return list<string>
     */
    public function getSynchronizeTables(): array
    {
        $value = $this->getString('synchronize_tables');
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $value))));
    }

    public function isTableSynchronizeEnabled(string $table): bool
    {
        return in_array($table, $this->getSynchronizeTables(), true);
    }

    public function isLockEnabled(): bool
    {
        return $this->getBool('lock');
    }

    public function isLockDescendantsEnabled(): bool
    {
        return $this->isLockEnabled() && $this->getBool('lock_descendants');
    }

    public function isLastSegmentOnlyEnabled(): bool
    {
        return $this->getBool('last_segment_only');
    }

    public function isFullPathEditingEnabled(): bool
    {
        return $this->getBool('allow_full_path_editing');
    }

    /**
     * @return list<int>
     */
    public function getExcludedPageTypes(): array
    {
        $value = $this->getString('exclude_doktypes');
        if ($value === '') {
            return [];
        }

        return array_values(array_map(intval(...), array_filter(explode(',', $value))));
    }

    public function isPageTypeExcluded(int $pageType): bool
    {
        return in_array($pageType, $this->getExcludedPageTypes(), true);
    }

    public function isCopyUrlEnabled(): bool
    {
        return $this->getBool('copy_url');
    }

    public function isPreserveUnderscoreEnabled(): bool
    {
        return $this->getBool('preserve_underscore');
    }

    public function isRedirectControlEnabled(): bool
    {
        return $this->getBool('redirect_control', true);
    }

    private function getBool(string $path, bool $default = false): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get(extension: 'sluggi', path: $path);
        } catch (Exception) {
            return $default;
        }
    }

    private function getString(string $path, string $default = ''): string
    {
        try {
            return (string)$this->extensionConfiguration->get(extension: 'sluggi', path: $path);
        } catch (Exception) {
            return $default;
        }
    }
}
