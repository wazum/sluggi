<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Controller;

use TYPO3\CMS\Backend\Controller\FormSlugAjaxController as CoreFormSlugAjaxController;
use TYPO3\CMS\Core\Information\Typo3Version;

if ((new Typo3Version())->getMajorVersion() >= 14) {
    return;
}

/**
 * Controller for TYPO3 12/13 (Not Readonly).
 *
 * @deprecated Remove this class when dropping TYPO3 12 support
 */
final class FormSlugAjaxControllerV12 extends CoreFormSlugAjaxController
{
    use FormSlugAjaxControllerTrait;
}
