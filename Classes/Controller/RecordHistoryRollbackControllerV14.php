<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Controller;

use TYPO3\CMS\Backend\History\RecordHistoryRollback;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Redirects\Controller\RecordHistoryRollbackController as CoreRecordHistoryRollbackController;
use TYPO3\CMS\Redirects\Service\TemporaryPermissionMutationService;

if ((new Typo3Version())->getMajorVersion() < 14) {
    return;
}

final readonly class RecordHistoryRollbackControllerV14 extends CoreRecordHistoryRollbackController
{
    use RecordHistoryRollbackControllerTrait;

    public function __construct(
        private LanguageServiceFactory $sluggiLanguageServiceFactory,
        RecordHistoryRollback $recordHistoryRollback,
        TemporaryPermissionMutationService $temporaryPermissionMutationService,
    ) {
        parent::__construct(
            $sluggiLanguageServiceFactory,
            $recordHistoryRollback,
            $temporaryPermissionMutationService,
        );
    }

    protected function createLanguageService(): LanguageService
    {
        return $this->sluggiLanguageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
    }
}
