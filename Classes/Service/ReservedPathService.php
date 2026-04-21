<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

final readonly class ReservedPathService
{
    public function __construct(
        private SiteFinder $siteFinder,
    ) {
    }

    /**
     * @param list<string> $patterns
     */
    public function isReserved(string $slug, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($slug === $pattern || str_starts_with($slug, $pattern . '/')) {
                return true;
            }
        }

        return false;
    }

    public function findSiteForPage(int $pageId): ?Site
    {
        try {
            return $this->siteFinder->getSiteByPageId($pageId);
        } catch (SiteNotFoundException) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    public function getReservedPathsForSite(Site $site): array
    {
        $tree = $site->getSettings()->getAll();
        $raw = $tree['sluggi']['reservedPaths'] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        return self::normalisePatterns($raw);
    }

    /**
     * @param array<int|string, mixed> $input
     *
     * @return list<string>
     */
    public static function normalisePatterns(array $input): array
    {
        $normalised = [];
        foreach ($input as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            $trimmed = rtrim($entry, '/');
            if (!str_starts_with($trimmed, '/')) {
                continue;
            }
            $normalised[] = $trimmed;
        }

        return $normalised;
    }
}
