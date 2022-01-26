<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Hook;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class DispatchNotificationHook
{
    /**
     * Called as a hook in \TYPO3\CMS\Backend\Utility\BackendUtility::getUpdateSignalCode
     * calls a JS function to send the slug change notification
     */
    public function dispatchNotification(array $params)
    {
        $javaScriptRenderer = GeneralUtility::makeInstance(PageRenderer::class)->getJavaScriptRenderer();
        $javaScriptRenderer->addJavaScriptModuleInstruction(
            JavaScriptModuleInstruction::forRequireJS('TYPO3/CMS/Sluggi/EventHandler')
                ->addFlags(JavaScriptModuleInstruction::FLAG_USE_TOP_WINDOW)
                ->invoke('dispatchCustomEvent', 'typo3:redirects:slugChanged', $params['parameter'])
        );
    }
}
