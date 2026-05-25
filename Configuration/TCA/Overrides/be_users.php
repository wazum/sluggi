<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// addUserSetting() was introduced in TYPO3 v14.3. Older TYPO3 versions
// register the field through the legacy API in ext_tables.php.
if (!method_exists(ExtensionManagementUtility::class, 'addUserSetting')) {
    return;
}

ExtensionManagementUtility::addUserSetting(
    'sluggiCollapsedControls',
    [
        'label' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:userSettings.collapsedControls',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
        ],
    ],
    'after:showHiddenFilesAndFolders'
);
