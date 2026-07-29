<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Event;

use InvalidArgumentException;

final class ModifySlugGenerationSourceFieldsEvent
{
    /**
     * Uniqueness is evaluated against a record state built from the untouched
     * record, so changing one of these would make the state and the generated
     * path describe different records.
     */
    private const IDENTITY_FIELDS = ['uid', 'pid', 'sys_language_uid', 'l10n_parent'];

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $sourceFieldValues
     */
    public function __construct(
        private readonly array $record,
        private readonly string $tableName,
        private readonly string $fieldName,
        private readonly int $pid,
        private readonly int $workspaceId,
        private readonly array $configuration,
        private array $sourceFieldValues = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getRecord(): array
    {
        return $this->record;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getWorkspaceId(): int
    {
        return $this->workspaceId;
    }

    /**
     * @return array<string, mixed> the configuration of this call site, which may differ from the effective one
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSourceFieldValues(): array
    {
        return $this->sourceFieldValues;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function setSourceFieldValues(array $values): void
    {
        $identityFields = array_intersect(array_keys($values), self::IDENTITY_FIELDS);
        if ($identityFields !== []) {
            throw new InvalidArgumentException(sprintf('A slug source field listener must not change the fields %s.', implode(', ', $identityFields)), 1785321600);
        }

        $this->sourceFieldValues = [...$this->sourceFieldValues, ...$values];
    }
}
