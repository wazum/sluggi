<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\HierarchyPermissionService;

final readonly class ValidateHierarchyPermission
{
    public function __construct(
        private HierarchyPermissionService $hierarchyPermissionService,
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

        if (!is_int($id) && !ctype_digit($id)) {
            return;
        }

        if ($this->extensionConfiguration->isLastSegmentOnlyEnabled()) {
            return;
        }

        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if ($backendUser === null || $backendUser->isAdmin()) {
            return;
        }

        $pageId = (int)$id;
        $record = BackendUtility::getRecord('pages', $pageId, 'slug');
        if ($record === null) {
            return;
        }

        $oldSlug = (string)$record['slug'];
        $newSlug = (string)$fieldArray['slug'];

        $lockedPrefix = $this->hierarchyPermissionService->getLockedPrefixForPage($pageId, $oldSlug);

        if ($lockedPrefix === '') {
            return;
        }

        if (!$this->hierarchyPermissionService->validateSlugChange($lockedPrefix, $oldSlug, $newSlug)) {
            unset($fieldArray['slug']);

            $dataHandler->log(
                'pages',
                $pageId,
                2,
                null,
                1,
                'Slug change blocked: You cannot modify path segments above your permission level.'
            );

            $this->addFlashMessage(
                'You cannot modify path segments above your permission level.',
                'Slug change blocked'
            );
        }
    }

    private function addFlashMessage(string $message, string $title): void
    {
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            $title,
            ContextualFeedbackSeverity::ERROR,
            true
        );

        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $flashMessageService->getMessageQueueByIdentifier()->enqueue($flashMessage);
    }
}
