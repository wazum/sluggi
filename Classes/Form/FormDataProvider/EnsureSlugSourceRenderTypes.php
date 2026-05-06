<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Form\FormDataProvider;

use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use Wazum\Sluggi\Service\SlugConfigurationService;

final readonly class EnsureSlugSourceRenderTypes implements FormDataProviderInterface
{
    public function __construct(
        private SlugConfigurationService $slugConfigurationService,
    ) {
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    public function addData(array $result): array
    {
        $columns = $result['processedTca']['columns'] ?? null;
        if (!is_array($columns)) {
            return $result;
        }

        $slugFieldName = $this->getSlugFieldName($columns);
        if ($slugFieldName === null) {
            return $result;
        }

        $fields = $columns[$slugFieldName]['config']['generatorOptions']['fields'] ?? [];
        if (!is_array($fields)) {
            return $result;
        }

        $fieldMetadata = $this->slugConfigurationService->getFieldMetadataFromFieldsConfig($fields);
        foreach ($this->slugConfigurationService->getSourceFieldsFromFieldsConfig($fields) as $fieldName) {
            if (!isset($result['processedTca']['columns'][$fieldName]['config'])) {
                continue;
            }

            $result['processedTca']['columns'][$fieldName]['config']['renderType'] = 'slugSourceInput';
            $result['processedTca']['columns'][$fieldName]['config']['sluggiSourceMetadata'] = $fieldMetadata[$fieldName] ?? null;
            $result['processedTca']['columns'][$fieldName]['config']['sluggiSourceTotalFields'] = count($fieldMetadata);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $columns
     */
    private function getSlugFieldName(array $columns): ?string
    {
        foreach ($columns as $fieldName => $fieldConfig) {
            if (($fieldConfig['config']['type'] ?? '') === 'slug') {
                return (string)$fieldName;
            }
        }

        return null;
    }
}
