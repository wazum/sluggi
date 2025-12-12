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
}
