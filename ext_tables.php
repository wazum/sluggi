<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$GLOBALS['TYPO3_USER_SETTINGS']['columns']['sluggiCollapsedControls'] = [
    'type' => 'check',
    'label' => 'LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:userSettings.collapsedControls',
];

ExtensionManagementUtility::addFieldsToUserSettings(
    'sluggiCollapsedControls',
    'after:showHiddenFilesAndFolders'
);
