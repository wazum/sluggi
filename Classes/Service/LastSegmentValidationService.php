<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Utility\SlugUtility;

final readonly class LastSegmentValidationService
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    public function shouldRestrictUser(bool $isAdmin): bool
    {
        if (!$this->extensionConfiguration->isLastSegmentOnlyEnabled()) {
            return false;
        }

        return !$isAdmin;
    }

    public function validateSlugChange(string $oldSlug, string $newSlug): bool
    {
        return SlugUtility::getParentPath($oldSlug) === SlugUtility::getParentPath($newSlug);
    }
}
