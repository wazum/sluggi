<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Helper;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

final class PermissionHelper
{
    public static function hasFullPermission(): bool
    {
        $backendUser = self::getBackendUser();
        if ($backendUser->isAdmin()) {
            return true;
        }

        $groupWhitelist = \explode(',', (string) Configuration::get('whitelist'));
        foreach ($groupWhitelist as $groupId) {
            if ($backendUser->isMemberOfGroup((int) $groupId)) {
                return true;
            }
        }

        return false;
    }

    public static function isLocked(array $page): bool
    {
        return (bool) $page['slug_locked'];
    }

    public static function hasSlugLockAccess(): bool
    {
        return self::getBackendUser()->check('non_exclude_fields', 'pages:slug_locked');
    }

    public static function getTopmostAccessiblePage(int $pageId): ?array
    {
        $rootLine = BackendUtility::BEgetRootLine(
            $pageId,
            '',
            false,
            [
                'perms_userid',
                'perms_groupid',
                'perms_user',
                'perms_group',
                'perms_everybody',
            ]
        );
        foreach (\array_reverse($rootLine) as $page) {
            // The root line includes the page with ID 0 now, so we kick that out
            if (($page['uid'] > 0) && self::getBackendUser()->doesUserHaveAccess($page, Permission::PAGE_EDIT)) {
                return $page;
            }
        }

        return null;
    }

    private static function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
