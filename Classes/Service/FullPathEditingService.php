<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;

final readonly class FullPathEditingService
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->extensionConfiguration->isFullPathEditingEnabled();
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function isAllowedForRequest(array $fieldArray, string $table): bool
    {
        if (!($fieldArray['tx_sluggi_full_path'] ?? false)) {
            return false;
        }

        if (!$this->isEnabled()) {
            return false;
        }

        $backendUser = $this->getBackendUser();

        return $backendUser !== null
            && $backendUser->check('non_exclude_fields', $table . ':tx_sluggi_full_path');
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
