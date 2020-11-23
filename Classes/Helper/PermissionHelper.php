<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Helper;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

/**
 * Class PermissionHelper
 *
 * @package Wazum\Sluggi\Helper
 * @author  Wolfgang Klinger <wolfgang@wazum.com>
 */
class PermissionHelper
{
    /**
     * @return bool
     */
    public static function hasFullPermission(): bool
    {
        $backendUser = self::getBackendUser();
        if ($backendUser->isAdmin()) {
            return true;
        }

        $groupWhitelist = explode(',', (string)Configuration::get('whitelist'));
        foreach ($groupWhitelist as $groupId) {
            if ($backendUser->isMemberOfGroup((int)$groupId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $page
     * @return bool
     */
    public static function isLocked(array $page): bool
    {
        return (bool)$page['tx_sluggi_lock'];
    }

    /**
     * Returns the topmost accessible page from the
     * current root line
     *
     * @param int $pageId
     * @return null|array
     */
    public static function getTopmostAccessiblePage(int $pageId): ?array
    {
        $backendUser = self::getBackendUser();

        $rootLine = BackendUtility::BEgetRootLine(
            $pageId,
            '',
            false,
            // Does not work anymore since TYPO3 9.5.14
            // see https://forge.typo3.org/issues/90104 which introduced this bug
            [
                'perms_userid',
                'perms_groupid',
                'perms_user',
                'perms_group',
                'perms_everybody',
            ]
        );
        $rootLine = array_reverse($rootLine);

        foreach ($rootLine as $page) {
            // Add missing fields (see above)
            if (!isset($page['perms_userid'])) {
                $missingFields = BackendUtility::getRecord('pages', $page['uid'],
                    implode(',', [
                        'perms_userid',
                        'perms_groupid',
                        'perms_user',
                        'perms_group',
                        'perms_everybody',
                    ])
                );
                if (is_array($missingFields)) {
                    $page = array_merge($page, $missingFields);
                }
            }
            // The root line includes the page with ID 0 now, so we kick that out
            if (($page['uid'] > 0) && $backendUser->doesUserHaveAccess($page, Permission::PAGE_EDIT)) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @return BackendUserAuthentication
     */
    protected static function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
