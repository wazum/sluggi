<?php
declare(strict_types=1);

namespace Wazum\Sluggi\Helper;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * Class PermissionHelper
 *
 * @package Wazum\Sluggi\Helper
 * @author Wolfgang Klinger <wolfgang@wazum.com>
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

        $groupWhitelist = [];
        try {
            $groupWhitelist = explode(',',
                GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('sluggi',
                    'whitelist'));
        } catch (ExtensionConfigurationExtensionNotConfiguredException $e) {
        } catch (ExtensionConfigurationPathDoesNotExistException $e) {
        }
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
        return (bool)$page['tx_sluggi_locked'];
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
            [
                'perms_userid',
                'perms_groupid',
                'perms_user',
                'perms_group',
                'perms_everybody'
            ]
        );
        $rootLine = array_reverse($rootLine);

        foreach ($rootLine as $page) {
            if ($backendUser->doesUserHaveAccess($page, Permission::PAGE_EDIT)) {
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
