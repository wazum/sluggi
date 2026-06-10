<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Utility\DataHandlerUtility;

final readonly class SlugLockService
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    public function isLockFeatureEnabled(): bool
    {
        return $this->extensionConfiguration->isLockEnabled();
    }

    /**
     * @param array<string, mixed> $record
     */
    public function isLocked(array $record): bool
    {
        if (!$this->isLockFeatureEnabled()) {
            return false;
        }

        return $this->getLockValue($record);
    }

    /**
     * Get the effective lock value, respecting l10n_mode inheritance for translations.
     *
     * @param array<string, mixed> $record
     */
    public function getLockValue(array $record): bool
    {
        $languageId = DataHandlerUtility::integerFieldValue($record, 'sys_language_uid');
        $l10nParent = DataHandlerUtility::integerFieldValue($record, 'l10n_parent');

        if ($languageId > 0 && $l10nParent > 0) {
            $parentRecord = BackendUtility::getRecordWSOL('pages', $l10nParent, 'slug_locked');
            if ($parentRecord !== null) {
                return (bool)($parentRecord['slug_locked'] ?? false);
            }
        }

        return (bool)($record['slug_locked'] ?? false);
    }

    public function hasLockedAncestor(int $pageId): bool
    {
        if (!$this->extensionConfiguration->isLockDescendantsEnabled()) {
            return false;
        }

        $rootLine = BackendUtility::BEgetRootLine($pageId, '', true, ['slug_locked']);

        foreach ($rootLine as $page) {
            if ((int)$page['uid'] === $pageId) {
                continue;
            }
            if ($page['slug_locked'] ?? false) {
                return true;
            }
        }

        return false;
    }
}
