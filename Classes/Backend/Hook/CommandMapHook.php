<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Hook;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Helper\SlugHelper;
use function abs;
use function rtrim;

/**
 * Class CommandMapHook
 *
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class CommandMapHook
{
    /**
     * @param int|string $id
     * @param bool|array $pasteUpdate
     * @param string|int $value
     */
    public function processCmdmap_postProcess(
        string $command,
        string $table,
        $id,
        $value,
        DataHandler $dataHandler,
        $pasteUpdate,
        array &$pasteDatamap
    ): void {
        if ('copy' !== $command ||
            'pages' !== $table
        ) {
            return;
        }
        $value = (int) $value;

        // Important: No spaces in the fields list!!
        $currentPage = BackendUtility::getRecord('pages', $id, 'uid,slug,sys_language_uid,tx_sluggi_lock,tx_sluggi_sync');
        if (!empty($currentPage)) {
            $languageId = $currentPage['sys_language_uid'];
            $currentSlugSegment = SlugHelper::getLastSlugSegment($currentPage['slug']);
            // Positive value = paste into
            if ($value > 0) {
                $parentPage = $value;
            } else {
                // Negative value = paste after
                $parentPage = BackendUtility::getRecord('pages', abs($value), 'pid')['pid'] ?? 0;
            }
            $parentSlug = SlugHelper::getSlug($parentPage, $languageId);
            $newSlug = rtrim($parentSlug, '/') . $currentSlugSegment;

            $targetPageId = $dataHandler->copyMappingArray['pages'][$id] ?? 0;
            if ($targetPageId) {
                $pasteDatamap['pages'][$targetPageId]['slug'] = $newSlug;
            }
        }
    }
}
