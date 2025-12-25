<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler\Fixtures;

/**
 * Test postModifier that strips 'strip/' prefix from slugs.
 * PostModifiers receive slugs WITHOUT leading slash (both TYPO3 core and sluggi).
 */
final class TestSlugPostModifier
{
    /**
     * @param array{slug: string, workspaceId: int, configuration: array<string, mixed>, record: array<string, mixed>, pid: int, prefix: string, tableName: string, fieldName: string} $params
     */
    public function stripPrefix(array $params): string
    {
        $slug = $params['slug'];

        if (str_starts_with($slug, 'strip/')) {
            return substr($slug, 6);
        }

        if ($slug === 'strip') {
            return '';
        }

        return $slug;
    }
}
