<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Form\FormDataProvider;

use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;

final readonly class HideSlugForExcludedPageTypes implements FormDataProviderInterface
{
    public function __construct(
        private ExtensionConfiguration $config,
    ) {
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    public function addData(array $result): array
    {
        if ($result['tableName'] !== 'pages') {
            return $result;
        }

        $pageType = (int)($result['databaseRow']['doktype'] ?? 1);
        if (is_array($result['databaseRow']['doktype'])) {
            $pageType = (int)($result['databaseRow']['doktype'][0] ?? 1);
        }

        if ($this->config->isPageTypeExcluded($pageType)) {
            unset($result['processedTca']['columns']['slug']);
        }

        return $result;
    }
}
