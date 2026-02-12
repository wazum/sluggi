<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Controller;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Redirects\Controller\RecordHistoryRollbackController as CoreRecordHistoryRollbackController;

if ((new Typo3Version())->getMajorVersion() < 14) {
    return;
}

final readonly class RecordHistoryRollbackControllerV14 extends CoreRecordHistoryRollbackController
{
    use RecordHistoryRollbackControllerTrait;

    public function __construct(
        private LanguageServiceFactory $sluggiLanguageServiceFactory,
        mixed ...$parentArgs,
    ) {
        parent::__construct($sluggiLanguageServiceFactory, ...$parentArgs);
    }

    protected function createLanguageService(): LanguageService
    {
        return $this->sluggiLanguageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
    }
}
