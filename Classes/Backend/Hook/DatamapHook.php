<?php
declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Hook;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Helper\PermissionHelper;
use Wazum\Sluggi\Helper\SlugHelper as SluggiSlugHelper;

/**
 * Class DatamapHook
 * @package Wazum\Sluggi\Backend\Hook
 * @author Wolfgang Klinger <wolfgang@wazum.com>
 */
class DatamapHook
{

    /**
     * @param string $status
     * @param string $table
     * @param integer $id
     * @param array $fieldArray
     * @param DataHandler $dataHandler
     */
    public function processDatamap_postProcessFieldArray(
        /** @noinspection PhpUnusedParameterInspection */
        $status,
        $table,
        $id,
        &$fieldArray,
        DataHandler $dataHandler
    ): void {
        if ($table === 'pages' &&
            !empty($fieldArray['slug']) &&
            !PermissionHelper::hasFullPermission()) {
            // @todo
            $mountRootPage = PermissionHelper::getTopmostAccessiblePage($id);
            $inaccessibleSlugSegments = SluggiSlugHelper::getSlug($mountRootPage['pid']);
            $fieldArray['slug'] = $inaccessibleSlugSegments . $fieldArray['slug'];
        }
    }

}
