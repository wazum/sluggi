<?php
declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Form;

use Wazum\Sluggi\Helper\PermissionHelper;

/**
 * Class InputSlugElement
 * @package Wazum\Sluggi\Backend\Form
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class InputSlugElement extends \TYPO3\CMS\Backend\Form\Element\InputSlugElement
{

    /**
     * Additional group/admin check for slug edit button
     *
     * @return array
     */
    public function render(): array
    {
        $result = parent::render();

        if ($this->data['tableName'] === 'pages' &&
            !PermissionHelper::backendUserHasPermission()) {
            // Remove slug edit button
            $result['html'] = preg_replace(
                '/<button class=".*?t3js-form-field-slug-toggle.*?">.*?<\/button>/ius', '', $result['html']);
        }

        return $result;
    }

}
