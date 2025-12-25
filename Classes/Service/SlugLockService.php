<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;

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
        return $this->isLockFeatureEnabled()
            && ($record['slug_locked'] ?? false);
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
