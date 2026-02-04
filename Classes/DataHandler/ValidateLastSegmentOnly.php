<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Service\FullPathEditingService;
use Wazum\Sluggi\Service\LastSegmentValidationService;
use Wazum\Sluggi\Service\SlugGeneratorService;
use Wazum\Sluggi\Utility\DataHandlerUtility;
use Wazum\Sluggi\Utility\SlugUtility;

final readonly class ValidateLastSegmentOnly
{
    public function __construct(
        private LastSegmentValidationService $validationService,
        private FullPathEditingService $fullPathEditingService,
        private SlugGeneratorService $slugGeneratorService,
    ) {
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_preProcessFieldArray(
        array &$fieldArray,
        string $table,
        string|int $id,
        DataHandler $dataHandler,
    ): void {
        if ($table !== 'pages' || !isset($fieldArray['slug'])) {
            return;
        }

        $backendUser = $this->getBackendUser();
        if ($backendUser === null) {
            return;
        }

        if (!$this->validationService->shouldRestrictUser($backendUser->isAdmin())) {
            return;
        }

        if ($this->fullPathEditingService->isAllowedForRequest($fieldArray, $table)) {
            return;
        }

        if (DataHandlerUtility::isNestedSlugUpdate($dataHandler)) {
            return;
        }

        $newSlug = (string)$fieldArray['slug'];
        $isNewRecord = DataHandlerUtility::isNewRecord($id);

        $expectedParentPath = $isNewRecord
            ? $this->resolveParentSlugForNewRecord($fieldArray)
            : $this->resolveParentSlugForExistingRecord((int)$id);

        if ($expectedParentPath === null) {
            return;
        }

        if (SlugUtility::getParentPath($newSlug) === $expectedParentPath) {
            return;
        }

        if ($isNewRecord) {
            $fieldArray['slug'] = $expectedParentPath . '/' . SlugUtility::getLastSegment($newSlug);
        } else {
            unset($fieldArray['slug']);
            DataHandlerUtility::logSlugValidationError($dataHandler, (int)$id, 'error.lastSegmentOnly');
        }
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    private function resolveParentSlugForNewRecord(array $fieldArray): ?string
    {
        $pid = (int)($fieldArray['pid'] ?? 0);
        if ($pid <= 0) {
            return null;
        }

        return $this->slugGeneratorService->getParentSlug($pid);
    }

    private function resolveParentSlugForExistingRecord(int $id): ?string
    {
        $record = BackendUtility::getRecordWSOL('pages', $id, 'slug');

        return $record !== null
            ? SlugUtility::getParentPath((string)$record['slug'])
            : null;
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
