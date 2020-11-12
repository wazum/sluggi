<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Hook;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Helper\SlugHelper;

/**
 * Class DatamapHook
 *
 * @package Wazum\Sluggi\Backend\Hook
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class DatamapHook
{
    /**
     * @param string $table
     * @param int $id
     * @param int $targetId
     * @param int $siblingTargetId
     * @param array $moveRecord
     * @param array $updateFields
     * @param DataHandler $dataHandler
     */
    public function moveRecord_afterAnotherElementPostProcess(
        /** @noinspection PhpUnusedParameterInspection */
        string $table,
        int $id,
        int $targetId,
        int $siblingTargetId,
        array $moveRecord,
        array $updateFields,
        DataHandler $dataHandler
    ): void {
        if ($table !== 'pages') {
            return;
        }

        $this->updateSlugForMovedPage($id, $targetId, $dataHandler);
    }

    /**
     * @param string $table
     * @param int $id
     * @param int $targetId
     * @param array $moveRecord
     * @param array $updateFields
     * @param DataHandler $dataHandler
     */
    public function moveRecord_firstElementPostProcess(
        /** @noinspection PhpUnusedParameterInspection */
        string $table,
        int $id,
        int $targetId,
        array $moveRecord,
        array $updateFields,
        DataHandler $dataHandler
    ): void {
        if ($table !== 'pages') {
            return;
        }

        $this->updateSlugForMovedPage($id, $targetId, $dataHandler);
    }

    protected function updateSlugForMovedPage(int $id, int $targetId, DataHandler $dataHandler): void
    {
        $currentPage = BackendUtility::getRecord('pages', $id, 'uid, slug, sys_language_uid');
        if (!empty($currentPage)) {
            $languageId = $currentPage['sys_language_uid'];
            $currentSlugSegment = $this->getLastSlugSegment($currentPage['slug']);
            $parentSlug = SlugHelper::getSlug($targetId, $languageId);
            $newSlug = rtrim($parentSlug, '/') . $currentSlugSegment;

            $data = [];
            $data['pages'][$id]['slug'] = $newSlug;
            /** @var DataHandler $dataHandler */
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($data, []);
            $dataHandler->setCorrelationId($dataHandler->getCorrelationId());
            $dataHandler->process_datamap();
        }
    }

    protected function getLastSlugSegment(string $slug): string
    {
        $parts = explode('/', $slug);

        return '/' . array_pop($parts);
    }
}