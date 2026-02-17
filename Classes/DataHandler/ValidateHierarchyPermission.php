<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\FullPathEditingService;
use Wazum\Sluggi\Service\HierarchyPermissionService;
use Wazum\Sluggi\Service\SlugGeneratorService;
use Wazum\Sluggi\Utility\DataHandlerUtility;
use Wazum\Sluggi\Utility\SlugUtility;

final readonly class ValidateHierarchyPermission
{
    public function __construct(
        private HierarchyPermissionService $hierarchyPermissionService,
        private FullPathEditingService $fullPathEditingService,
        private SlugGeneratorService $slugGeneratorService,
        private ExtensionConfiguration $extensionConfiguration,
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

        if ($this->extensionConfiguration->isLastSegmentOnlyEnabled()) {
            return;
        }

        $backendUser = $this->getBackendUser();
        if ($backendUser === null || $backendUser->isAdmin()) {
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

        $lockedPrefix = $isNewRecord
            ? $this->resolveLockedPrefixForNewRecord($fieldArray)
            : $this->resolveLockedPrefixForExistingRecord((int)$id);

        if ($lockedPrefix === null || $lockedPrefix === '') {
            return;
        }

        if ($this->isWithinAllowedHierarchy($lockedPrefix, $newSlug)) {
            return;
        }

        if (!$isNewRecord && $this->isLastSegmentOnlyChange((int)$id, $newSlug)) {
            return;
        }

        if ($isNewRecord) {
            $fieldArray['slug'] = SlugUtility::enforceParentPath($lockedPrefix, $newSlug);
        } else {
            unset($fieldArray['slug']);
            DataHandlerUtility::logSlugValidationError($dataHandler, (int)$id, 'error.hierarchyPermission');
        }
    }

    private function isWithinAllowedHierarchy(string $lockedPrefix, string $newSlug): bool
    {
        return $this->hierarchyPermissionService->validateSlugChange($lockedPrefix, $newSlug);
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    private function resolveLockedPrefixForNewRecord(array $fieldArray): ?string
    {
        $pid = (int)($fieldArray['pid'] ?? 0);
        if ($pid <= 0) {
            return null;
        }

        $parentSlug = $this->slugGeneratorService->getParentSlug($pid);
        $parentRecord = BackendUtility::getRecordWSOL('pages', $pid, 'slug');
        $currentParentSlug = (string)($parentRecord['slug'] ?? $parentSlug);

        return $this->hierarchyPermissionService->getLockedPrefixForPage($pid, $currentParentSlug);
    }

    private function resolveLockedPrefixForExistingRecord(int $id): ?string
    {
        $record = BackendUtility::getRecordWSOL('pages', $id, 'slug');
        if ($record === null) {
            return null;
        }

        return $this->hierarchyPermissionService->getLockedPrefixForPage($id, (string)$record['slug']);
    }

    private function isLastSegmentOnlyChange(int $id, string $newSlug): bool
    {
        $currentRecord = BackendUtility::getRecordWSOL('pages', $id, 'slug');
        if ($currentRecord === null) {
            return false;
        }

        return SlugUtility::getParentPath($newSlug) === SlugUtility::getParentPath((string)$currentRecord['slug']);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
