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
use Wazum\Sluggi\Utility\DataHandlerUtility;
use Wazum\Sluggi\Utility\FlashMessageUtility;

final readonly class ValidateHierarchyPermission
{
    public function __construct(
        private HierarchyPermissionService $hierarchyPermissionService,
        private FullPathEditingService $fullPathEditingService,
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

        if (!is_int($id) && !ctype_digit($id)) {
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

        $pageId = (int)$id;
        $record = BackendUtility::getRecordWSOL('pages', $pageId, 'slug');
        if ($record === null) {
            return;
        }

        $oldSlug = (string)$record['slug'];
        $newSlug = (string)$fieldArray['slug'];

        $lockedPrefix = $this->hierarchyPermissionService->getLockedPrefixForPage($pageId, $oldSlug);

        if ($lockedPrefix === '') {
            return;
        }

        if (!$this->hierarchyPermissionService->validateSlugChange($lockedPrefix, $newSlug)) {
            unset($fieldArray['slug']);

            $title = $this->translate('error.hierarchyPermission.title');
            $message = $this->translate('error.hierarchyPermission.message');

            $dataHandler->log(
                'pages',
                $pageId,
                2,
                null,
                1,
                $title . ': ' . $message
            );

            FlashMessageUtility::addError($message, $title);
        }
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
