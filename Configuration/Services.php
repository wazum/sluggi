<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Backend\Controller\FormSlugAjaxController as CoreFormSlugAjaxController;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Redirects\Controller\RecordHistoryRollbackController as CoreRecordHistoryRollbackController;
use Wazum\Sluggi\Controller\FormSlugAjaxControllerV14;
use Wazum\Sluggi\Controller\RecordHistoryRollbackControllerV14;

return static function (ContainerConfigurator $container): void {
    if ((new Typo3Version())->getMajorVersion() < 14) {
        return;
    }

    $services = $container->services();

    $services->set(CoreFormSlugAjaxController::class)
        ->class(FormSlugAjaxControllerV14::class)
        ->autowire()
        ->public();

    $services->set(CoreRecordHistoryRollbackController::class)
        ->class(RecordHistoryRollbackControllerV14::class)
        ->autowire()
        ->public();
};
