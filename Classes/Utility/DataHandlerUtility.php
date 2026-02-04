<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Utility;

use TYPO3\CMS\Core\DataHandling\DataHandler;
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
}
