<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\Helper\Configuration;
use Wazum\Sluggi\Helper\PermissionHelper;
use Wazum\Sluggi\Helper\SlugHelper;

final class HandleNewPage
{
    public function processDatamap_preProcessFieldArray(
        array &$fields,
        string $table,
        string|int $id,
        DataHandler $dataHandler
    ): void {
        if (!$this->shouldRun($table, $id, $fields)) {
            return;
        }

        $lastSegment = SlugHelper::getLastSlugSegment($fields['slug']);
        $languageId = (int)($fields['sys_language_uid'] ?? 0);
        $parentSlug = SlugHelper::getSlug((int)$fields['pid'], $languageId);

        $fields['slug'] = \rtrim($parentSlug, '/') . $lastSegment;
    }

    private function shouldRun(string $table, string|int $id, array $fields): bool
    {
        $pid = (int)($fields['pid'] ?? 0);
        // Only run for new pages with valid pid
        if ($table !== 'pages' || (int)$id !== 0 || !($pid > 0)) {
            return false;
        }

        // Only run if the slug field is set
        if (empty($fields['slug'] ?? '')) {
            return false;
        }

        // Only run if the user does not have full permissions and last_segment_only is enabled
        $lastSegmentOnly = (bool)Configuration::get('last_segment_only');
        return $lastSegmentOnly && !PermissionHelper::hasFullPermission();
    }
}
