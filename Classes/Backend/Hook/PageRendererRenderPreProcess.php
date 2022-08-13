<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Hook;

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class PageRendererRenderPreProcess
 *
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
final class PageRendererRenderPreProcess
{
    public function addRequireJsConfiguration(array $params, PageRenderer $pageRenderer): void
    {
        if ($pageRenderer->getApplicationType() === 'BE') {
            $pageRenderer->loadRequireJsModule('TYPO3/CMS/Sluggi/Sluggi');
        }
    }
}
