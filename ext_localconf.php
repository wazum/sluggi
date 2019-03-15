<?php

defined('TYPO3_MODE') or die();

(static function () {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Form\Element\InputSlugElement::class] = [
        'className' => \Wazum\Sluggi\Backend\Form\InputSlugElement::class
    ];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Controller\FormSlugAjaxController::class] = [
        'className' => \Wazum\Sluggi\Backend\Controller\FormSlugAjaxController::class
    ];
    $GLOBALS ['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
        \Wazum\Sluggi\Backend\Hook\DatamapHook::class;
})();
