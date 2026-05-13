<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Hooks;

use TYPO3\CMS\Backend\Domain\Model\Element\ImmediateActionElement;

final class DispatchSlugChangeReport
{
    /**
     * @param array{set: string, parameter: mixed, html: string} $params
     */
    public function dispatch(array &$params): void
    {
        $parameter = is_array($params['parameter'] ?? null) ? $params['parameter'] : [];
        $params['html'] = (string)ImmediateActionElement::dispatchCustomEvent(
            'typo3:sluggi:slugChangeReport',
            $parameter,
            true,
        );
    }
}
