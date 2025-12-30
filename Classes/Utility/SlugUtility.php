<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Utility;

final class SlugUtility
{
    public static function getLastSegment(string $slug): string
    {
        $slug = trim($slug, '/');
        if ($slug === '') {
            return '';
        }

        $parts = explode('/', $slug);

        return array_pop($parts);
    }

    public static function getParentPath(string $slug): string
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
