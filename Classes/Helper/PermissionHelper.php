<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Helper;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use function array_reverse;
use function explode;

/**
 * Class PermissionHelper
 *
 * @author  Wolfgang Klinger <wolfgang@wazum.com>
 */
class PermissionHelper
{
    public static function hasFullPermission(): bool
    {
        $backendUser = self::getBackendUser();
        if ($backendUser->isAdmin()) {
            return true;
        }

        $groupWhitelist = explode(',', (string) Configuration::get('whitelist'));
        foreach ($groupWhitelist as $groupId) {
            if ($backendUser->isMemberOfGroup((int) $groupId)) {
                return true;
            }
        }

        return false;
    }

    protected static function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    public static function isLocked(array $page): bool
    {
        return (bool) $page['tx_sluggi_lock'];
    }

    /**
     * Returns the topmost accessible page from the
     * current root line
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
        foreach (array_reverse($rootLine) as $page) {
            // The root line includes the page with ID 0 now, so we kick that out
            if (($page['uid'] > 0) && self::getBackendUser()->doesUserHaveAccess($page, Permission::PAGE_EDIT)) {
                return $page;
            }
        }

        return null;
    }
}
