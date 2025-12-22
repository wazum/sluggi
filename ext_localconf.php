<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\FormSlugAjaxController as CoreFormSlugAjaxController;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessCommon;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Page\PageRenderer;
use Wazum\Sluggi\Controller\FormSlugAjaxController;
use Wazum\Sluggi\DataHandler\HandlePageCopy;
use Wazum\Sluggi\DataHandler\HandlePageMove;
use Wazum\Sluggi\DataHandler\HandlePageUpdate;
use Wazum\Sluggi\DataHandler\ValidateHierarchyPermission;
use Wazum\Sluggi\DataHandler\ValidateLastSegmentOnly;
use Wazum\Sluggi\Form\Element\SlugElement;
use Wazum\Sluggi\Form\Element\SlugSourceElement;
use Wazum\Sluggi\Form\FormDataProvider\InitializeSyncField;

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
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][InitializeSyncField::class] = [
    'depends' => [TcaColumnsProcessCommon::class],
];

// XCLASS
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][CoreFormSlugAjaxController::class] = [
    'className' => FormSlugAjaxController::class,
];

// PageRenderer hook for CSS
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_pagerenderer.php']['render-preProcess']['sluggi'] =
    static function (array &$params, PageRenderer $pageRenderer): void {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request instanceof ServerRequestInterface && ApplicationType::fromRequest($request)->isBackend()) {
            $pageRenderer->addCssFile('EXT:sluggi/Resources/Public/Css/sluggi-source-badge.css');
        }
    };

// DataHandler hooks
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi'] =
    HandlePageUpdate::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi_validate'] =
    ValidateLastSegmentOnly::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi_hierarchy'] =
    ValidateHierarchyPermission::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['moveRecordClass']['sluggi'] =
    HandlePageMove::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['sluggi'] =
    HandlePageCopy::class;
