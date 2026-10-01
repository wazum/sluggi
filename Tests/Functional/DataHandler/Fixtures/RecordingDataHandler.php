<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler\Fixtures;

use TYPO3\CMS\Core\DataHandling\DataHandler;

final class RecordingDataHandler extends DataHandler
{
    /**
     * @var list<int|string>
     */
    public static array $writtenPageIds = [];

    public function start($dataMap, $commandMap, ...$arguments): void
    {
        foreach (array_keys($dataMap['pages'] ?? []) as $pageId) {
            self::$writtenPageIds[] = $pageId;
        }
        parent::start($dataMap, $commandMap, ...$arguments);
    }
}
