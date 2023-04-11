<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend;

final class SlugModifier extends \B13\Masi\SlugModifier
{
    protected function resolveHookParameters(array $configuration, string $tableName, string $fieldName, int $pid, int $workspaceId, array $record): void
    {
        parent::resolveHookParameters($configuration, $tableName, $fieldName, $pid, $workspaceId, $record);
        // Do not fetch the record again from the database
        $this->recordData = $record;
    }
}
