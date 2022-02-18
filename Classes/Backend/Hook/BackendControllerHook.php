<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Hook;

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class BackendControllerHook
 *
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class BackendControllerHook
{
    /**
     * Register a custom event handler with a timeout on the notifications
     */
    public function registerClientSideEventHandler(): void
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addInlineLanguageLabelFile('EXT:redirects/Resources/Private/Language/locallang_slug_service.xlf');
    }
}
