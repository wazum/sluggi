<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Redirects\Service\SlugService;

/**
 * Workaround for TYPO3 13.4 core bug: missing page-level access for redirect storage.
 *
 * TemporaryPermissionMutationService grants tables_modify for sys_redirect but does not
 * grant page-level access. Editors without the site root page in their webmounts or
 * without CONTENT_EDIT permission on the root page cannot create redirect records
 * (stored at pid = site root page ID).
 *
 * No-op on TYPO3 12 and 14 where redirect creation bypasses DataHandler.
 */
final class BypassAccessCheckForRedirectCreation
{
    public function processDatamap_beforeStart(DataHandler $dataHandler): void
    {
        if (self::isRedirectCreation($dataHandler)) {
            $dataHandler->bypassAccessCheckForRecords = true;
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
