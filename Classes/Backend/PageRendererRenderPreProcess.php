<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend;

use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Page\PageRenderer;

final class PageRendererRenderPreProcess
{
    /**
     * @param array<array-key, mixed> $params
     */
    public function run(array $params, PageRenderer $pageRenderer): void
    {
        if (!$this->isBackend() || !$this->isPageEdit()) {
            return;
        }

        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Sluggi/sluggi');
    }

    private function isBackend(): bool
    {
        return ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isBackend();
    }

    private function isPageEdit(): bool
    {
        return isset($_GET['edit']['pages']);
    }
}
