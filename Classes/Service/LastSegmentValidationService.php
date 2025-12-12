<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use Wazum\Sluggi\Configuration\ExtensionConfiguration;

final readonly class LastSegmentValidationService
{
    public function __construct(
        private ExtensionConfiguration $config,
    ) {
    }

    public function shouldRestrictUser(bool $isAdmin): bool
    {
        if (!$this->config->isLastSegmentOnlyEnabled()) {
            return false;
        }

        return !$isAdmin;
    }

    public function validateSlugChange(string $oldSlug, string $newSlug): bool
    {
        return $this->getParentPath($oldSlug) === $this->getParentPath($newSlug);
    }

    private function getParentPath(string $slug): string
    {
        $slug = trim($slug, '/');
        if ($slug === '') {
            return '';
        }

        $parts = explode('/', $slug);
        array_pop($parts);

        return $parts === [] ? '' : '/' . implode('/', $parts);
    }
}
