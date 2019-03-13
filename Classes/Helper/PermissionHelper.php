<?php
declare(strict_types=1);

namespace Wazum\Sluggi\Helper;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Class PermissionHelper
 * @package Wazum\Sluggi\Helper
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class PermissionHelper
{

    /**
     * @return bool
     */
    public static function backendUserHasPermission(): bool
    {
        /** @var BackendUserAuthentication $BE_USER */
        global $BE_USER;

        if ($BE_USER->isAdmin()) {
            return true;
        }

        $groupWhitelist = explode(',',
            \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('sluggi',
                'whitelist'));
        foreach ($groupWhitelist as $groupId) {
            if ($BE_USER->isMemberOfGroup((int)$groupId)) {
                return true;
            }
        }

        return false;
    }

}
