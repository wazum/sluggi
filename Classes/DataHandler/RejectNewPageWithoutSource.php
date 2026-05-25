<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Service\SlugConfigurationService;
use Wazum\Sluggi\Service\SlugSyncService;
use Wazum\Sluggi\Utility\DataHandlerUtility;

/**
 * Cancels new-page inserts that match the v14 wizard's empty-submit signature
 * (sync on, all slug source-field keys absent from the incoming array, and no
 * TCA or userTS default would supply one), preventing meaningless
 * "/parent-slug-N" fallback slugs.
 *
 * Runs in preProcessFieldArray because core's `continue 2` cleanly skips the
 * record when the incoming array is no longer an array. Setting `$fieldArray
 * = null` in postProcessFieldArray would crash core's strict-typed
 * afterDatabaseOperations hooks.
 */
final readonly class RejectNewPageWithoutSource
{
    public function __construct(
        private SlugSyncService $syncService,
        private SlugConfigurationService $configurationService,
    ) {
    }

    /**
     * @param array<string, mixed>|null $incomingFieldArray
     */
    public function processDatamap_preProcessFieldArray(
        ?array &$incomingFieldArray,
        string $table,
        string|int $id,
        DataHandler $dataHandler,
    ): void {
        if ($table !== 'pages' || !is_array($incomingFieldArray)) {
            return;
        }
        if (!DataHandlerUtility::isNewRecord($id)) {
            return;
        }
        if (!$this->syncService->shouldSync($incomingFieldArray)) {
            return;
        }

        $sourceFields = $this->configurationService->getSourceFields($table);

        // Empty-but-present is an explicit submission, not the wizard bug.
        foreach ($sourceFields as $field) {
            if (array_key_exists($field, $incomingFieldArray)) {
                return;
            }
        }

        // Core merges TCA and userTS defaults into the field array after this
        // hook runs; defer to those before deciding to reject.
        foreach ($sourceFields as $field) {
            $tcaDefault = trim((string)($GLOBALS['TCA'][$table]['columns'][$field]['config']['default'] ?? ''));
            $userTsDefault = trim((string)($dataHandler->defaultValues[$table][$field] ?? ''));
            if ($tcaDefault !== '' || $userTsDefault !== '') {
                return;
            }
        }

        DataHandlerUtility::logSlugValidationError($dataHandler, 0, 'error.emptySourceOnNewPage');
        $incomingFieldArray = null;
    }
}
