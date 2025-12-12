<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

final readonly class SlugElementRenderer
{
    /**
     * @param array<string, mixed>  $context
     * @param array<string, string> $labels
     *
     * @return array<string, string>
     */
    public function buildAttributes(array $context, array $labels): array
    {
        $attributes = [
            'value' => $context['decodedValue'],
            'page-id' => (string)$context['effectivePid'],
            'record-id' => (string)$context['recordId'],
            'table-name' => $context['table'],
            'field-name' => $context['fieldName'],
        ];

        $attributes['language'] = (string)$context['languageId'];
        $attributes['signature'] = $context['signature'];
        $attributes['command'] = $context['command'];
        $attributes['parent-page-id'] = (string)$context['parentPageId'];
        $attributes['fallback-character'] = $context['fallbackCharacter'];
        $attributes['labels'] = (string)json_encode($labels);

        if ($context['includeUid']) {
            $attributes['include-uid'] = '';
        }
        if ($context['hasPostModifiers']) {
            $attributes['has-post-modifiers'] = '';
        }
        if (!empty($context['requiredSourceFields'])) {
            $attributes['required-source-fields'] = implode(',', $context['requiredSourceFields']);
        }
        if ($context['syncFeatureEnabled'] ?? false) {
            $attributes['sync-feature-enabled'] = '';
        }
        if ($context['isSynced']) {
            $attributes['is-synced'] = '';
        }
        if ($context['lastSegmentOnly'] ?? false) {
            $attributes['last-segment-only'] = '';
        }

        return $attributes;
    }

    /** @param array<string, string> $attributes */
    public function buildAttributeString(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $name => $value) {
            if ($value === '') {
                $parts[] = $name;
            } else {
                $parts[] = sprintf('%s="%s"', $name, htmlspecialchars($value, ENT_QUOTES | ENT_HTML5));
            }
        }

        return $parts !== [] ? ' ' . implode(' ', $parts) : '';
    }

    public function buildSyncFieldName(string $table, int|string $recordId): string
    {
        return sprintf('data[%s][%s][tx_sluggi_sync]', $table, $recordId);
    }
}
