<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

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
}
