<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\FullPathEditingService;
use Wazum\Sluggi\Service\HierarchyPermissionService;
use Wazum\Sluggi\Service\SlugGeneratorService;
use Wazum\Sluggi\Utility\DataHandlerUtility;
use Wazum\Sluggi\Utility\FlashMessageUtility;
use Wazum\Sluggi\Utility\SlugUtility;

final readonly class ValidateHierarchyPermission
{
    public function __construct(
        private HierarchyPermissionService $hierarchyPermissionService,
        private FullPathEditingService $fullPathEditingService,
        private SlugGeneratorService $slugGeneratorService,
        private ExtensionConfiguration $extensionConfiguration,
        private LanguageServiceFactory $languageServiceFactory,
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

        $lockedPrefix = $isNewRecord
            ? $this->resolveLockedPrefixForNewRecord($fieldArray)
            : $this->resolveLockedPrefixForExistingRecord((int)$id);

        if ($lockedPrefix === null || $lockedPrefix === '') {
            return;
        }

        if ($this->hierarchyPermissionService->validateSlugChange($lockedPrefix, $newSlug)) {
            return;
        }

        if ($isNewRecord) {
            $fieldArray['slug'] = rtrim($lockedPrefix, '/') . '/' . SlugUtility::getLastSegment($newSlug);
        } else {
            unset($fieldArray['slug']);
            $this->logValidationError($dataHandler, (int)$id);
        }
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

    private function logValidationError(DataHandler $dataHandler, int $id): void
    {
        $title = $this->translate('error.hierarchyPermission.title');
        $message = $this->translate('error.hierarchyPermission.message');

        $dataHandler->log('pages', $id, 2, null, 1, $title . ': ' . $message);
        FlashMessageUtility::addError($message, $title);
    }

    private function translate(string $key): string
    {
        $backendUser = $this->getBackendUser();
        if ($backendUser === null) {
            return $key;
        }

        return $this->languageServiceFactory
            ->createFromUserPreferences($backendUser)
            ->sL('LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:' . $key);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
