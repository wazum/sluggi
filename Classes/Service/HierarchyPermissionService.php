<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use Wazum\Sluggi\Utility\SlugUtility;

final readonly class HierarchyPermissionService
{
    /**
     * @param array<int, array<string, mixed>> $rootLine         TYPO3 rootLine (current page to root)
     * @param array<int>                       $editablePageUids
     */
    public function getLockedPrefix(
        array $rootLine,
        array $editablePageUids,
        string $currentSlug,
    ): string {
        if ($editablePageUids === []) {
            return SlugUtility::getParentPath($currentSlug);
        }

        foreach (array_reverse($rootLine) as $page) {
            $uid = (int)($page['uid'] ?? 0);
            $slug = (string)($page['slug'] ?? '');

            // Skip root page - it has no meaningful parent path for prefix calculation
            if ($slug === '/' || $slug === '') {
                continue;
            }

            if ($uid > 0 && in_array($uid, $editablePageUids, true)) {
                return SlugUtility::getParentPath($slug);
            }
        }

        return SlugUtility::getParentPath($currentSlug);
    }

    public function validateSlugChange(string $lockedPrefix, string $oldSlug, string $newSlug): bool
    {
        if ($lockedPrefix === '') {
            return true;
        }

        $lockedPrefix = rtrim($lockedPrefix, '/');

        return str_starts_with($newSlug, $lockedPrefix . '/') || $newSlug === $lockedPrefix;
    }

    /**
     * @param array<int, array<string, mixed>>     $rootLine
     * @param callable(array<string, mixed>): bool $permissionCheck
     *
     * @return array<int>
     */
    public function getEditablePageUids(
        array $rootLine,
        callable $permissionCheck,
    ): array {
        $editableUids = [];

        foreach ($rootLine as $page) {
            $uid = (int)($page['uid'] ?? 0);
            if ($uid > 0 && $permissionCheck($page)) {
                $editableUids[] = $uid;
            }
        }

        return $editableUids;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getRootLineWithPermissions(int $pageId): array
    {
        return BackendUtility::BEgetRootLine(
            $pageId,
            '',
            false,
            [
                'perms_userid',
                'perms_groupid',
                'perms_user',
                'perms_group',
                'perms_everybody',
                'slug',
            ]
        );
    }

    public function getLockedPrefixForPage(int $pageId, string $currentSlug): string
    {
        $backendUser = $this->getBackendUser();
        if ($backendUser === null || $backendUser->isAdmin()) {
            return '';
        }

        $rootLine = $this->getRootLineWithPermissions($pageId);
        $editableUids = $this->getEditablePageUids(
            $rootLine,
            fn (array $page): bool => $backendUser->doesUserHaveAccess($page, Permission::PAGE_EDIT)
        );

        return $this->getLockedPrefix($rootLine, $editableUids, $currentSlug);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
