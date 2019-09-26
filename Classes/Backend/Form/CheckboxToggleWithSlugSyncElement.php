<?php
declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Form;

use TYPO3\CMS\Backend\Form\Element\CheckboxToggleElement;

/**
 * Class CheckboxToggleWithSlugSyncElement
 *
 * @package Wazum\Sluggi\Backend\Form
 * @author  Wolfgang Klinger <wolfgang@wazum.com>
 */
class CheckboxToggleWithSlugSyncElement extends CheckboxToggleElement
{
    /**
     * @return array
     */
    public function render(): array
    {
        $result = parent::render();
        // Add a CSS class
        $result['html'] = preg_replace('/(<label.*?class=")(checkbox-label)" (for=".*?tx_sluggi_sync.*?")/im',
            '$1$2 slug-sync" $3', $result['html']);

        return $result;
    }
}
