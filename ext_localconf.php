<?php

declare(strict_types=1);

defined('TYPO3') || exit;

(static function (): void {
    // Register some DataHandler hooks for page related actions
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi']
        = \Wazum\Sluggi\DataHandler\HandlePageUpdate::class;

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][]
        = \Wazum\Sluggi\DataHandler\HandlePageCopy::class;

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['moveRecordClass'][]
        = \Wazum\Sluggi\DataHandler\HandlePageMove::class;

    // Load custom JavaScript
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_pagerenderer.php']['render-preProcess'][]
        = \Wazum\Sluggi\Backend\PageRendererRenderPreProcess::class . '->run';

    // Render custom options for slug fields
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Form\Element\InputSlugElement::class] = [
        'className' => \Wazum\Sluggi\Backend\Form\InputSlugElement::class,
    ];

    // Use a custom slug service
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Redirects\Service\SlugService::class] = [
        'className' => \Wazum\Sluggi\Service\SlugService::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1569661269] = [
        'nodeName' => 'inputTextWithSlugImpact',
        'priority' => 40,
        'class' => \Wazum\Sluggi\Backend\Form\InputTextWithSlugImpactElement::class,
    ];

    // Modify feature related TCA on the fly
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][\Wazum\Sluggi\Backend\FormDataProvider::class] = [
        'depends' => [
            \TYPO3\CMS\Backend\Form\FormDataProvider\EvaluateDisplayConditions::class,
        ],
    ];

    // Overwrite the controller that returns slug suggestions
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Controller\FormSlugAjaxController::class] = [
        'className' => \Wazum\Sluggi\Backend\Controller\FormSlugAjaxController::class,
    ];

    if (\Wazum\Sluggi\Helper\Configuration::get('disable_slug_update_information')) {
        unset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['updateSignalHook']['redirects:slugChanged']);
    }

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['slug_lock_upgrade_wizard']
        = \Wazum\Sluggi\Upgrade\SlugLockUpgradeWizard::class;
})();
