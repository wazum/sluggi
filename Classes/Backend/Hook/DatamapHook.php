<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Hook;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Helper\Configuration;
use Wazum\Sluggi\Helper\PermissionHelper;
use Wazum\Sluggi\Helper\SlugHelper;
use function rtrim;

/**
 * Class DatamapHook
 *
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class DatamapHook
{
    public function moveRecord_afterAnotherElementPostProcess(
        /* @noinspection PhpUnusedParameterInspection */
        string $table,
        int $id,
        int $targetId,
        int $siblingTargetId,
        array $moveRecord,
        array $updateFields,
        DataHandler $dataHandler
    ): void {
        if ('pages' !== $table) {
            return;
        }

        $this->updateSlugForMovedPage($id, $targetId, $dataHandler);
    }

    protected function updateSlugForMovedPage(int $id, int $targetId, DataHandler $dataHandler): void
    {
        // Important: No spaces in the fields list!!
        $currentPage = BackendUtility::getRecordWSOL('pages', $id, 'uid,slug,sys_language_uid,tx_sluggi_lock');
        if (!empty($currentPage) && !PermissionHelper::isLocked($currentPage)) {
            $allowOnlyLastSegment = (bool) Configuration::get('last_segment_only');

            $currentSlugSegment = SlugHelper::getLastSlugSegment($currentPage['slug']);
            if ($allowOnlyLastSegment && !PermissionHelper::hasFullPermission()) {
                $newSlug = $currentSlugSegment;
            } else {
                $languageId = $currentPage['sys_language_uid'];
                $parentSlug = SlugHelper::getSlug($targetId, $languageId);
                $newSlug = rtrim($parentSlug, '/') . $currentSlugSegment;
            }

            $data = [];
            $data['pages'][$id]['slug'] = $newSlug;
            /** @var DataHandler $localDataHandler */
            $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $localDataHandler->start($data, []);
            $localDataHandler->setCorrelationId($dataHandler->getCorrelationId());
            $localDataHandler->process_datamap();
        }
    }

    public function moveRecord_firstElementPostProcess(
        /* @noinspection PhpUnusedParameterInspection */
        string $table,
        int $id,
        int $targetId,
        array $moveRecord,
        array $updateFields,
        DataHandler $dataHandler
    ): void {
        if ('pages' !== $table) {
            return;
        }

        $this->updateSlugForMovedPage($id, $targetId, $dataHandler);
    }
}
