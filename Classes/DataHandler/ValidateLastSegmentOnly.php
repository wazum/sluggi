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

        if (!$isNewRecord && DataHandlerUtility::isSlugUnchanged((int)$id, $newSlug)) {
            return;
        }

        $expectedParentPath = $isNewRecord
            ? $this->resolveParentSlugForNewRecord($fieldArray)
            : $this->resolveParentSlugForExistingRecord((int)$id);

        if ($expectedParentPath === null) {
            return;
        }

        if ($this->hasValidParentPath($newSlug, $expectedParentPath)) {
            return;
        }

        if ($isNewRecord) {
            $fieldArray['slug'] = SlugUtility::enforceParentPath($expectedParentPath, $newSlug);
        } else {
            unset($fieldArray['slug']);
            DataHandlerUtility::logSlugValidationError($dataHandler, (int)$id, 'error.lastSegmentOnly');
        }
    }

    private function hasValidParentPath(string $newSlug, string $expectedParentPath): bool
    {
        return SlugUtility::getParentPath($newSlug) === $expectedParentPath;
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
        $record = BackendUtility::getRecordWSOL('pages', $id, 'pid,sys_language_uid');
        if ($record === null) {
            return null;
        }

        $languageId = (int)($record['sys_language_uid'] ?? 0);

        return $this->slugGeneratorService->getParentSlug((int)$record['pid'], $languageId);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
