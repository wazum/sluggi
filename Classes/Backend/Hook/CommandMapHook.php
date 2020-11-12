<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Hook;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Helper\SlugHelper;
use function rtrim;

/**
 * Class CommandMapHook
 *
 * @package Wazum\Sluggi\Backend\Hook
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class CommandMapHook
{
    /**
     * @param int|string $id
     * @param bool|array $pasteUpdate
     * @param string|array $value
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
        if ($command !== 'copy' ||
            $table !== 'pages'
        ) {
            return;
        }

        $currentPage = BackendUtility::getRecord('pages', $id, 'uid, slug, sys_language_uid');
        if (!empty($currentPage)) {
            $languageId = $currentPage['sys_language_uid'];
            $currentSlugSegment = SlugHelper::getLastSlugSegment($currentPage['slug']);
            // Positive value = paste into
            if ($value > 0) {
                $parentPage = (int)$value;
            } else {
                $parentPage = BackendUtility::getRecord('pages', abs($value), 'pid')['pid'] ?? 0;
                // Negative value = paste after
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