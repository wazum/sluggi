<?php
declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Form;

use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use Wazum\Sluggi\Helper\PermissionHelper;

/**
 * Class FormDataProvider
 * @package Wazum\Sluggi\Backend\Form
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class FormDataProvider implements FormDataProviderInterface
{

    /**
     * @param array $result
     * @return array
     */
    public function addData(array $result): array
    {
        if ($result['tableName'] === 'pages' && PermissionHelper::backendUserHasPermission()) {
            // Remove the superfluous field
            foreach (['title', 'titleonly'] as $palette) {
                $result['processedTca']['palettes'][$palette]['showitem'] = str_replace(', tx_sluggi_segment, --linebreak--', '', $result['processedTca']['palettes'][$palette]['showitem']);
            }
        }

        return $result;
    }

}
