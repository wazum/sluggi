<?php

declare(strict_types=1);

use TYPO3\CMS\Backend\Form\FormDataProvider\EvaluateDisplayConditions;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Wazum\Sluggi\Backend\Controller\FormSlugAjaxController;
use Wazum\Sluggi\Backend\Form\InputSlugElement;
use Wazum\Sluggi\Backend\Form\InputTextWithSlugImpactElement;
use Wazum\Sluggi\Backend\FormDataProvider;
use Wazum\Sluggi\Backend\PageRendererRenderPreProcess;
use Wazum\Sluggi\DataHandler\HandleExcludeSlugForSubpages;
use Wazum\Sluggi\DataHandler\HandleNewPage;
use Wazum\Sluggi\DataHandler\HandlePageCopy;
use Wazum\Sluggi\DataHandler\HandlePageMove;
use Wazum\Sluggi\DataHandler\HandlePageUpdate;
use Wazum\Sluggi\Helper\Configuration;
use Wazum\Sluggi\Service\SlugService;

defined('TYPO3') || exit;

if (!function_exists('array_flatten')) {
    /**
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    function array_flatten(array $array): array
    {
        $merged = [[]];
        foreach ($array as $value) {
            if (is_array($value)) {
                $merged[] = array_flatten($value);
            } else {
                $merged[] = [$value];
            }
        }

        return array_merge([], ...$merged);
    }
}

(static function (): void {
    // Register some DataHandler hooks for page related actions
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi-new']
        = HandleNewPage::class;

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi-update']
        = HandlePageUpdate::class;

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['sluggi-copy']
        = HandlePageCopy::class;

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['moveRecordClass']['sluggi-move']
        = HandlePageMove::class;

    // Load custom JavaScript
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_pagerenderer.php']['render-preProcess'][]
        = PageRendererRenderPreProcess::class . '->run';

    // Render custom options for slug fields
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TYPO3\CMS\Backend\Form\Element\InputSlugElement::class] = [
        'className' => InputSlugElement::class,
    ];

    // Use a custom slug service
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TYPO3\CMS\Redirects\Service\SlugService::class] = [
        'className' => SlugService::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1569661269] = [
        'nodeName' => 'inputTextWithSlugImpact',
        'priority' => 40,
        'class' => InputTextWithSlugImpactElement::class,
    ];

    // Modify feature related TCA on the fly
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][FormDataProvider::class] = [
        'depends' => [
            EvaluateDisplayConditions::class,
        ],
    ];

    // Overwrite the controller that returns slug suggestions
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TYPO3\CMS\Backend\Controller\FormSlugAjaxController::class] = [
        'className' => FormSlugAjaxController::class,
    ];

    if (Configuration::get('disable_slug_update_information')) {
        unset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_befunc.php']['updateSignalHook']['redirects:slugChanged']);
    }

    if (ExtensionManagementUtility::isLoaded('masi')) {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi-exclude']
            = HandleExcludeSlugForSubpages::class;
    }
})();
