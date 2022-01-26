<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Form;

use TYPO3\CMS\Backend\Form\Element\InputTextElement;
use function preg_replace;

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
        // Add a special CSS class to identify these fields in JavaScript
        $result['html'] = preg_replace('/(<input.*?class=")(.*?)"/im', '$1$2 slug-impact"', $result['html']);

        return $result;
    }
}
