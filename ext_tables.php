<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// TYPO3 v14.3+ registers this field via the TCA-based addUserSetting() API in
// Configuration/TCA/Overrides/be_users.php. The legacy registration below is
// only used for TYPO3 v12 and v13.
if (method_exists(ExtensionManagementUtility::class, 'addUserSetting')) {
    return;
}

$GLOBALS['TYPO3_USER_SETTINGS']['columns']['sluggiCollapsedControls'] = [
    'type' => 'check',
    'label' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:userSettings.collapsedControls',
];

ExtensionManagementUtility::addFieldsToUserSettings(
    'sluggiCollapsedControls',
    'after:showHiddenFilesAndFolders'
);
