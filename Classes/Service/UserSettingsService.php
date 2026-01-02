<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

final readonly class UserSettingsService
{
    public function isCollapsedControlsEnabled(): bool
    {
        $backendUser = $this->getBackendUser();
        if ($backendUser === null) {
            return false;
        }

        return (bool)($backendUser->uc['sluggiCollapsedControls'] ?? false);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
