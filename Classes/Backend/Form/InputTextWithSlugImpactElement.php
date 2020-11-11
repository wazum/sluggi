<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Form;

use TYPO3\CMS\Backend\Form\Element\InputTextElement;

/**
 * Class InputTextWithSlugImpactElement
 *
 * @package Wazum\Sluggi\Backend\Form
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class InputTextWithSlugImpactElement extends InputTextElement
{
    public function render(): array
    {
        $result = parent::render();
        // Fix core bug and add space between attributes
        $result['html'] = str_replace('type="text"id=', 'type="text" id=', $result['html']);
        // Add a CSS class
        $result['html'] = preg_replace('/(<input.*?class=")(.*?)"/im', '$1$2 slug-impact"', $result['html']);

        return $result;
    }
}
