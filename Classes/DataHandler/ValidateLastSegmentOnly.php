<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Service\FullPathEditingService;
use Wazum\Sluggi\Service\LastSegmentValidationService;

final readonly class ValidateLastSegmentOnly
{
    public function __construct(
        private LastSegmentValidationService $validationService,
        private FullPathEditingService $fullPathEditingService,
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

        $backendUser = $this->getBackendUser();
        if ($backendUser === null) {
            return;
        }

        $isAdmin = $backendUser->isAdmin();
        if (!$this->validationService->shouldRestrictUser($isAdmin)) {
            return;
        }

        if ($this->fullPathEditingService->isAllowedForRequest($fieldArray, $table)) {
            unset($fieldArray['tx_sluggi_full_path']);

            return;
        }

        $record = BackendUtility::getRecordWSOL('pages', (int)$id, 'slug');
        if ($record === null) {
            return;
        }

        $oldSlug = (string)$record['slug'];
        $newSlug = (string)$fieldArray['slug'];

        if (!$this->validationService->validateSlugChange($oldSlug, $newSlug)) {
            unset($fieldArray['slug']);

            $dataHandler->log(
                'pages',
                (int)$id,
                2,
                null,
                1,
                'Slug change blocked: You can only edit the last segment of the URL slug.'
            );

            $this->addFlashMessage(
                'You can only edit the last segment of the URL slug.',
                'Slug change blocked'
            );
        }
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
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
