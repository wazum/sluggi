<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Backend;

use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Helper\Configuration;

final class FormDataProvider implements FormDataProviderInterface
{
    /**
     * @param array<array-key, mixed> $result
     *
     * @return array<array-key, mixed>
     */
    public function addData(array $result): array
    {
        $result = $this->removeSlugFieldsForExcludedPageTypes($result);
        $result = $this->removeSlugSynchronizationIfSlugIsLocked($result);

        return $result;
    }

    /**
     * @param array<array-key, mixed> $result
     *
     * @return array<array-key, mixed>
     */
    private function removeSlugFieldsForExcludedPageTypes(array $result): array
    {
        if ('pages' === $result['tableName']
            && in_array((int) ($result['databaseRow']['doktype'][0] ?? ''),
                GeneralUtility::intExplode(',', Configuration::get('exclude_page_types') ?? '', true), true)
        ) {
            unset(
                $result['processedTca']['columns']['slug'],
                $result['processedTca']['columns']['tx_sluggi_sync'],
                $result['processedTca']['columns']['slug_locked'],
                // Extension "masi"
                $result['processedTca']['columns']['exclude_slug_for_subpages']
            );
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $result
     *
     * @return array<array-key, mixed>
     */
    public function removeSlugSynchronizationIfSlugIsLocked(array $result): array
    {
        if ('pages' === $result['tableName']
            && $result['databaseRow']['slug_locked']
            && !$GLOBALS['BE_USER']->check('non_exclude_fields', 'pages:slug_locked')
        ) {
            unset($result['processedTca']['columns']['tx_sluggi_sync']);
        }

        return $result;
    }
}
