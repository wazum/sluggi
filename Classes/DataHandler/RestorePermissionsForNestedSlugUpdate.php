<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Redirects\Service\SlugService;
use Wazum\Sluggi\Utility\DataHandlerUtility;

/**
 * Workaround for two TYPO3 13.4.25 core bugs in redirect/slug cascade handling.
 *
 * Bug 1 (tables_modify corruption, fixed in 13.4.26):
 * TemporaryPermissionMutationService::removeSysRedirectPermission() receives the
 * groupData key name instead of the actual permission string, corrupting
 * $GLOBALS['BE_USER']->groupData['tables_modify'] after redirect creation.
 * This prevents SlugService::persistNewSlug() from updating child page slugs.
 * Fix: save tables_modify before processing, restore it for nested slug update DataHandlers.
 * On versions without the bug, the save/restore is a harmless no-op.
 *
 * Bug 2 (missing page-level access for redirect storage):
 * TemporaryPermissionMutationService grants tables_modify for sys_redirect but does not
 * grant page-level access. Editors without the site root page in their webmounts or
 * without CONTENT_EDIT permission on the root page cannot create redirect records
 * (stored at pid = site root page ID).
 * Fix: bypass page-level access checks for the redirect creation DataHandler.
 *
 * Both workarounds are no-ops on TYPO3 12 and 14 where redirect creation bypasses DataHandler.
 */
final class RestorePermissionsForNestedSlugUpdate
{
    private static ?string $savedTablesModify = null;

    public function processDatamap_beforeStart(DataHandler $dataHandler): void
    {
        if (!DataHandlerUtility::isNestedSlugUpdate($dataHandler)) {
            self::$savedTablesModify = $GLOBALS['BE_USER']->groupData['tables_modify'] ?? null;

            if (self::isRedirectCreation($dataHandler)) {
                $dataHandler->bypassAccessCheckForRecords = true;
            }

            return;
        }

        if (self::$savedTablesModify !== null) {
            $GLOBALS['BE_USER']->groupData['tables_modify'] = self::$savedTablesModify;
        }
    }

    private static function isRedirectCreation(DataHandler $dataHandler): bool
    {
        $correlationId = $dataHandler->getCorrelationId();
        if ($correlationId === null) {
            return false;
        }

        $aspects = $correlationId->getAspects();
        if (!in_array(SlugService::CORRELATION_ID_IDENTIFIER, $aspects, true)
            || !in_array('redirect', $aspects, true)) {
            return false;
        }

        $tables = array_keys($dataHandler->datamap ?? []);

        return $tables === ['sys_redirect'];
    }
}
