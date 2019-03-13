<?php

defined('TYPO3_MODE') or die();

(function () {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Form\Element\InputSlugElement::class] = [
        'className' => \Wazum\Sluggi\Backend\Form\InputSlugElement::class
    ];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][\Wazum\Sluggi\Backend\Form\FormDataProvider::class] = [
        'depends' => [
            \TYPO3\CMS\Backend\Form\FormDataProvider\InitializeProcessedTca::class
        ]
    ];
    $GLOBALS ['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
        \Wazum\Sluggi\Backend\Hook\DatamapHook::class;
})();
