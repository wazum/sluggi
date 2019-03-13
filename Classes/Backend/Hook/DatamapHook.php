<?php
declare(strict_types=1);

namespace Wazum\Sluggi\Backend\Hook;

use TYPO3\CMS\Core\DataHandling\DataHandler;

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
        if ($table === 'pages' && !empty($fieldArray['tx_sluggi_segment'])) {
            // Empty field
            $fieldArray['tx_sluggi_segment'] = '';
        }
    }

}
