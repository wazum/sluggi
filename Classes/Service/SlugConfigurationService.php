<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

final readonly class SlugConfigurationService
{
    /**
     * @return string[]
     */
    public function getSourceFields(string $table): array
    {
        $fields = $this->getNormalizedFieldsConfig($table);

        $result = [];
        foreach ($fields as $field) {
            if (is_array($field)) {
                $result = [...$result, ...$field];
            } else {
                $result[] = $field;
            }
        }

        return $result;
    }

    /**
     * Get detailed field metadata including fallback chain information.
     * For config [['nav_title', 'title'], 'subtitle'], returns:
     * [
     *     'nav_title' => ['slot' => 1, 'role' => 'preferred', 'chainSize' => 2],
     *     'title' => ['slot' => 1, 'role' => 'fallback', 'chainSize' => 2],
     *     'subtitle' => ['slot' => 2, 'role' => 'single', 'chainSize' => 1],
     * ].
     *
     * @return array<string, array{slot: int, role: string, chainSize: int}>
     */
    public function getFieldMetadata(string $table): array
    {
        $fields = $this->getNormalizedFieldsConfig($table);
        $result = [];
        $slot = 0;

        foreach ($fields as $field) {
            if (is_array($field)) {
                ++$slot;
                $chainSize = count($field);
                foreach ($field as $index => $chainField) {
                    $role = match (true) {
                        $chainSize === 1 => 'single',
                        $index === 0 => 'preferred',
                        default => 'fallback',
                    };
                    $result[$chainField] = [
                        'slot' => $slot,
                        'role' => $role,
                        'chainSize' => $chainSize,
                    ];
                }
            } else {
                ++$slot;
                $result[$field] = [
                    'slot' => $slot,
                    'role' => 'single',
                    'chainSize' => 1,
                ];
            }
        }

        return $result;
    }

    /**
     * Get the required fallback fields (last field in each fallback chain).
     * For config [['nav_title', 'title'], 'subtitle'], returns ['title', 'subtitle'].
     * These fields must have values for a valid slug to be generated.
     *
     * @return string[]
     */
    public function getRequiredSourceFields(string $table): array
    {
        $fields = $this->getNormalizedFieldsConfig($table);

        $result = [];
        foreach ($fields as $field) {
            if (is_array($field)) {
                $lastField = end($field);
                if ($lastField !== false) {
                    $result[] = $lastField;
                }
            } else {
                $result[] = $field;
            }
        }

        return $result;
    }

    /**
     * Normalizes field config by splitting comma-separated strings into arrays.
     * TYPO3 core supports both ['nav_title, title'] and [['nav_title', 'title']].
     *
     * @return array<int, string|string[]>
     */
    private function getNormalizedFieldsConfig(string $table): array
    {
        $slugFieldName = $this->getSlugFieldName($table);
        $fields = $GLOBALS['TCA'][$table]['columns'][$slugFieldName]['config']['generatorOptions']['fields'] ?? [];

        return array_map(
            static fn ($field) => is_string($field) && str_contains($field, ',')
                ? array_map('trim', explode(',', $field))
                : $field,
            $fields
        );
    }

    public function getSlugFieldName(string $table): ?string
    {
        foreach ($GLOBALS['TCA'][$table]['columns'] ?? [] as $fieldName => $fieldConfig) {
            if (($fieldConfig['config']['type'] ?? '') === 'slug') {
                return $fieldName;
            }
        }

        return null;
    }
}
