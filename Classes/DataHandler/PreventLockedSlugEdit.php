<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Service\SlugLockService;

final readonly class PreventLockedSlugEdit
{
    public function __construct(
        private SlugLockService $lockService,
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
        if ($table !== 'pages') {
            return;
        }

        if (!isset($fieldArray['slug'])) {
            return;
        }

        if (!is_numeric($id)) {
            return;
        }

        $record = BackendUtility::getRecordWSOL($table, (int)$id);
        if ($record === null) {
            return;
        }

        if ($this->isBeingUnlocked($fieldArray)) {
            return;
        }

        if ($this->lockService->isLocked($record) || $this->lockService->hasLockedAncestor((int)$id)) {
            unset($fieldArray['slug']);
        }
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    private function isBeingUnlocked(array $fieldArray): bool
    {
        return array_key_exists('slug_locked', $fieldArray)
            && !$fieldArray['slug_locked']
            && $this->canUserModifyLockField();
    }

    private function canUserModifyLockField(): bool
    {
        $backendUser = $this->getBackendUser();

        return $backendUser->isAdmin()
            || $backendUser->check('non_exclude_fields', 'pages:slug_locked');
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
