<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\FormSlugAjaxController as CoreFormSlugAjaxController;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessCommon;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Page\PageRenderer;
use Wazum\Sluggi\Controller\FormSlugAjaxController;
use Wazum\Sluggi\DataHandler\ClearSlugForExcludedDoktypes;
use Wazum\Sluggi\DataHandler\HandlePageCopy;
use Wazum\Sluggi\DataHandler\HandlePageMove;
use Wazum\Sluggi\DataHandler\HandlePageUpdate;
use Wazum\Sluggi\DataHandler\PreventLockedSlugEdit;
use Wazum\Sluggi\DataHandler\ValidateHierarchyPermission;
use Wazum\Sluggi\DataHandler\ValidateLastSegmentOnly;
use Wazum\Sluggi\Form\Element\SlugElement;
use Wazum\Sluggi\Form\Element\SlugSourceElement;
use Wazum\Sluggi\Form\FormDataProvider\HideSlugForExcludedPageTypes;
use Wazum\Sluggi\Form\FormDataProvider\InitializeSyncField;

// XCLASS
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][CoreFormSlugAjaxController::class] = [
    'className' => FormSlugAjaxController::class,
];

// Form elements
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1733600000] = [
    'nodeName' => 'sluggiSlug',
    'priority' => 50,
    'class' => SlugElement::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1733600001] = [
    'nodeName' => 'slugSourceInput',
    'priority' => 50,
    'class' => SlugSourceElement::class,
];

// Form data providers
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][HideSlugForExcludedPageTypes::class] = [
    'depends' => [TcaColumnsProcessCommon::class],
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][InitializeSyncField::class] = [
    'depends' => [TcaColumnsProcessCommon::class],
];

// PageRenderer hook
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_pagerenderer.php']['render-preProcess']['sluggi'] =
    static function (array &$params, PageRenderer $pageRenderer): void {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request instanceof ServerRequestInterface && ApplicationType::fromRequest($request)->isBackend()) {
            $pageRenderer->addCssFile('EXT:sluggi/Resources/Public/Css/sluggi-source-badge.css');
        }
    };

// DataHandler
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi_exclude_doktypes'] =
    ClearSlugForExcludedDoktypes::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi_hierarchy'] =
    ValidateHierarchyPermission::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi_lock'] =
    PreventLockedSlugEdit::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi_update'] =
    HandlePageUpdate::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi_validate'] =
    ValidateLastSegmentOnly::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['sluggi_copy'] =
    HandlePageCopy::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['moveRecordClass']['sluggi_move'] =
    HandlePageMove::class;
