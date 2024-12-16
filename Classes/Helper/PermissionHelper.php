<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Helper;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class PermissionHelper
{
    public static function hasFullPermission(): bool
    {
        if (self::isAdmin()) {
            return true;
        }

        $groupWhitelist = \explode(',', (string) Configuration::get('whitelist'));
        foreach ($groupWhitelist as $groupId) {
            if (self::isMemberOfGroup((int) $groupId)) {
                return true;
            }

            return false;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $page
     */
    public static function isLocked(array $page): bool
    {
        return (bool) $page['slug_locked'];
    }

    public static function hasSlugLockAccess(): bool
    {
        return self::getBackendUser()->check('non_exclude_fields', 'pages:slug_locked');
    }

    /**
     * @return array<string, mixed>|null
     */
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

    private static function getContext(): Context
    {
        return GeneralUtility::makeInstance(Context::class);
    }

    private static function isAdmin(): bool
    {
        return self::getContext()->getPropertyFromAspect('backend.user', 'isAdmin');
    }

    private static function isMemberOfGroup(int $groupId): bool
    {
        /** @var int[] $userGroupsUID */
        $userGroupUids = self::getContext()->getPropertyFromAspect('backend.user', 'groupIds');

        if (!empty($userGroupUids) && $groupId) {
            return in_array($groupId, $userGroupsUID, true);
        }

        return false;
    }
}
