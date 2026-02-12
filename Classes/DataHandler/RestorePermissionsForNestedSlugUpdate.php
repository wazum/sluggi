<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Utility\DataHandlerUtility;

/**
 * Workaround for TYPO3 core bug in TemporaryPermissionMutationService.
 *
 * The core's removeSysRedirectPermission() receives the groupData key name
 * ('tables_modify') instead of the actual permission string, corrupting
 * $GLOBALS['BE_USER']->groupData['tables_modify'] after redirect creation.
 * This prevents SlugService::persistNewSlug() from updating child page slugs
 * for non-admin users because DataHandler::checkModifyAccessList() fails.
 *
 * This hook saves the original tables_modify permission before processing and
 * restores it for nested slug update DataHandler instances (where the corruption
 * has already occurred).
 */
final class RestorePermissionsForNestedSlugUpdate
{
    private static ?string $savedTablesModify = null;

    public function processDatamap_beforeStart(DataHandler $dataHandler): void
    {
        if (!DataHandlerUtility::isNestedSlugUpdate($dataHandler)) {
            self::$savedTablesModify = $GLOBALS['BE_USER']->groupData['tables_modify'] ?? null;

            return;
        }

        if (self::$savedTablesModify !== null) {
            $GLOBALS['BE_USER']->groupData['tables_modify'] = self::$savedTablesModify;
        }
    }
}
