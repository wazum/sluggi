<?php

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Helper\Configuration;
use Wazum\Sluggi\Helper\PermissionHelper;
use Wazum\Sluggi\Helper\SlugHelper;

final class HandlePageMove
{
    /**
     * @param array<string, mixed> $moveRecord
     * @param array<string, mixed> $updateFields
     */
    public function moveRecord_afterAnotherElementPostProcess(
        /* @noinspection PhpUnusedParameterInspection */
        string $table,
        int $id,
        int $targetId,
        int $siblingTargetId,
        array $moveRecord,
        array $updateFields,
        DataHandler $dataHandler,
    ): void {
        if ('pages' !== $table) {
            return;
        }

        $this->updateSlugForMovedPage($id, $targetId, $dataHandler);
    }

    /**
     * @param array<string, mixed> $moveRecord
     * @param array<string, mixed> $updateFields
     */
    public function moveRecord_firstElementPostProcess(
        /* @noinspection PhpUnusedParameterInspection */
        string $table,
        int $id,
        int $targetId,
        array $moveRecord,
        array $updateFields,
        DataHandler $dataHandler,
    ): void {
        if ('pages' !== $table) {
            return;
        }

        $this->updateSlugForMovedPage($id, $targetId, $dataHandler);
    }

    private function updateSlugForMovedPage(int $id, int $targetId, DataHandler $dataHandler): void
    {
        // Important: No spaces in the fields list!!
        $currentPage = BackendUtility::getRecordWSOL('pages', $id, 'uid,slug,sys_language_uid,slug_locked,tx_sluggi_sync');
        if (empty($currentPage) || PermissionHelper::isLocked($currentPage)) {
            return;
        }

        $allowOnlyLastSegment = (bool) Configuration::get('last_segment_only');

        $currentSlugSegment = SlugHelper::getLastSlugSegment($currentPage['slug']);
        if ($allowOnlyLastSegment && !PermissionHelper::hasFullPermission()) {
            $newSlug = $currentSlugSegment;
        } else {
            $languageId = $currentPage['sys_language_uid'];
            $parentSlug = SlugHelper::getSlug($targetId, $languageId);
            $newSlug = \rtrim($parentSlug, '/') . $currentSlugSegment;
        }

        $data = [];
        $data['pages'][$id]['slug'] = $newSlug;
        $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $localDataHandler->start($data, []);
        $localDataHandler->setCorrelationId($dataHandler->getCorrelationId());
        $localDataHandler->process_datamap();
    }
}
