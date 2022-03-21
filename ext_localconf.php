<?php

defined('TYPO3_MODE') or die();

(static function () {
    if (TYPO3_MODE === 'BE') {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride']['EXT:redirects/Resources/Private/Language/locallang_slug_service.xlf'][]
            = 'EXT:sluggi/Resources/Private/Language/locallang_slug_service.xlf';

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1569661269] = [
            'nodeName' => 'inputTextWithSlugImpact',
            'priority' => 40,
            'class' => \Wazum\Sluggi\Backend\Form\InputTextWithSlugImpactElement::class
        ];
        /** @var \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer */
        $pageRenderer = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Page\PageRenderer::class);
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Sluggi/Sluggi');

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi']
            = \Wazum\Sluggi\Backend\Hook\DataHandlerSlugUpdateHook::class;

        $GLOBALS ['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['moveRecordClass'][]
            = \Wazum\Sluggi\Backend\Hook\DatamapHook::class;

        $GLOBALS ['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][]
            = \Wazum\Sluggi\Backend\Hook\CommandMapHook::class;

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Redirects\Service\SlugService::class] = [
            'className' => \Wazum\Sluggi\Backend\Service\SlugService::class
        ];

        // Register a different JS event handler with a timeout for the info notification
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/backend.php']['constructPostProcess'] as $key => $handler) {
            if ($handler === \TYPO3\CMS\Redirects\Hooks\BackendControllerHook::class . '->registerClientSideEventHandler') {
                $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/backend.php']['constructPostProcess'][$key]
                    = \Wazum\Sluggi\Backend\Hook\BackendControllerHook::class . '->registerClientSideEventHandler';
                break;
            }
        }

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Form\Element\InputSlugElement::class] = [
            'className' => \Wazum\Sluggi\Backend\Form\InputSlugElement::class
        ];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Controller\FormSlugAjaxController::class] = [
            'className' => \Wazum\Sluggi\Backend\Controller\FormSlugAjaxController::class
        ];

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][\Wazum\Sluggi\Backend\FormDataProvider\SanitizeSlugOptionsTca::class] = [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\InitializeProcessedTca::class
            ]
        ];
    }
})();
