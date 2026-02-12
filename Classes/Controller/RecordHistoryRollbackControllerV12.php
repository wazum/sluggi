<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Controller;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Redirects\Controller\RecordHistoryRollbackController as CoreRecordHistoryRollbackController;

if ((new Typo3Version())->getMajorVersion() >= 14) {
    return;
}

/**
 * @deprecated Remove this class when dropping TYPO3 12 support
 */
final class RecordHistoryRollbackControllerV12 extends CoreRecordHistoryRollbackController
{
    use RecordHistoryRollbackControllerTrait;

    protected function createLanguageService(): LanguageService
    {
        return $this->languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
    }
}
