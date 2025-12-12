<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Form\FormDataProvider;

use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;

final readonly class InitializeSyncField implements FormDataProviderInterface
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
        if ($result['command'] !== 'new' || $result['tableName'] !== 'pages') {
            return $result;
        }

        if ($this->config->isSyncEnabled()) {
            $result['databaseRow']['tx_sluggi_sync'] = 1;
        }

        return $result;
    }
}
