<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\FormSlugAjaxController as CoreFormSlugAjaxController;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessCommon;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Page\PageRenderer;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;
use Wazum\Sluggi\Controller\FormSlugAjaxControllerV12;
use Wazum\Sluggi\Controller\FormSlugAjaxControllerV14;
use Wazum\Sluggi\DataHandler\ClearSlugForExcludedDoktypes;
use Wazum\Sluggi\DataHandler\HandlePageCopy;
use Wazum\Sluggi\DataHandler\HandlePageMove;
use Wazum\Sluggi\DataHandler\HandlePageUpdate;
use Wazum\Sluggi\DataHandler\HandleRecordUpdate;
use Wazum\Sluggi\DataHandler\PreventLockedSlugEdit;
use Wazum\Sluggi\DataHandler\ValidateHierarchyPermission;
use Wazum\Sluggi\DataHandler\ValidateLastSegmentOnly;
use Wazum\Sluggi\Form\Element\SlugElementV12;
use Wazum\Sluggi\Form\Element\SlugElementV14;
use Wazum\Sluggi\Form\Element\SlugSourceElementV12;
use Wazum\Sluggi\Form\Element\SlugSourceElementV14;
use Wazum\Sluggi\Form\FormDataProvider\HideSlugForExcludedPageTypes;
use Wazum\Sluggi\Form\FormDataProvider\InitializeSyncField;

// XCLASS
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][CoreFormSlugAjaxController::class] = [
    'className' => Typo3Compatibility::getMajorVersion() >= 14
        ? FormSlugAjaxControllerV14::class
        : FormSlugAjaxControllerV12::class,
];

// Form elements
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1733600000] = [
    'nodeName' => 'sluggiSlug',
    'priority' => 50,
    'class' => Typo3Compatibility::getMajorVersion() >= 13
        ? SlugElementV14::class
        : SlugElementV12::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1733600001] = [
    'nodeName' => 'slugSourceInput',
    'priority' => 50,
    'class' => Typo3Compatibility::getMajorVersion() >= 13
        ? SlugSourceElementV14::class
        : SlugSourceElementV12::class,
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

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi_record_update'] =
    HandleRecordUpdate::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sluggi_validate'] =
    ValidateLastSegmentOnly::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['sluggi_copy'] =
    HandlePageCopy::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['moveRecordClass']['sluggi_move'] =
    HandlePageMove::class;

// Override core "URL Segment" label to "URL Path"
$GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride']['EXT:core/Resources/Private/Language/locallang_tca.xlf'][] =
    'EXT:sluggi/Resources/Private/Language/Overrides/locallang_tca.xlf';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride']['de']['EXT:core/Resources/Private/Language/locallang_tca.xlf'][] =
    'EXT:sluggi/Resources/Private/Language/Overrides/de.locallang_tca.xlf';
