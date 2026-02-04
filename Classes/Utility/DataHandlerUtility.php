<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Utility;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Redirects\Service\SlugService;

final class DataHandlerUtility
{
    public static function isNewRecord(string|int $id): bool
    {
        return !is_int($id) && !ctype_digit((string)$id);
    }

    public static function isNestedSlugUpdate(DataHandler $dataHandler): bool
    {
        $correlationId = $dataHandler->getCorrelationId();
        if ($correlationId === null) {
            return false;
        }

        return in_array(SlugService::CORRELATION_ID_IDENTIFIER, $correlationId->getAspects(), true);
    }

    public static function logSlugValidationError(
        DataHandler $dataHandler,
        int $id,
        string $errorKeyPrefix,
    ): void {
        $title = self::translate($errorKeyPrefix . '.title');
        $message = self::translate($errorKeyPrefix . '.message');

        $dataHandler->log('pages', $id, 2, null, 1, $title . ': ' . $message);
        FlashMessageUtility::addError($message, $title);
    }

    private static function translate(string $key): string
    {
        $backendUser = self::getBackendUser();
        if ($backendUser === null) {
            return $key;
        }

        return GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($backendUser)
            ->sL('LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:' . $key);
    }

    private static function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
