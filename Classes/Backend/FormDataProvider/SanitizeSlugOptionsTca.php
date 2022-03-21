<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend\FormDataProvider;

use TYPO3\CMS\Backend\Form\FormDataProviderInterface;

/**
 * Class SanitizeSlugOptionsTca
 *
 * @author Wolfgang Klinger <wk@plan2.net>
 */
final class SanitizeSlugOptionsTca implements FormDataProviderInterface
{
    public function addData(array $result): array
    {
        // If the slug is locked, remove the synchronization switch if the user has no access to the lock field
        if ('pages' === $result['tableName'] &&
            $result['databaseRow']['tx_sluggi_lock'] &&
            !$GLOBALS['BE_USER']->check('non_exclude_fields', 'pages:tx_sluggi_lock')
        ) {
            unset($result['processedTca']['columns']['tx_sluggi_sync']);
        }

        return $result;
    }
}