<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class SlugGeneratorService
{
    /** @param array<string, mixed> $record */
    public function generate(array $record, int $pid): string
    {
        $slugHelper = $this->getSlugHelper();
        $fieldConfig = $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? [];
        $sanitizedRecord = $this->sanitizeSourceFieldValues($record, $fieldConfig);

        $state = RecordStateFactory::forName('pages')
            ->fromArray($sanitizedRecord, $pid, $sanitizedRecord['uid'] ?? 0);
        $slug = $slugHelper->generate($sanitizedRecord, $pid);

        try {
            return '/' . ltrim($slugHelper->buildSlugForUniqueInSite($slug, $state), '/');
        } catch (SiteNotFoundException) {
            return '';
        }
    }

    /**
     * Replace slashes in source field values with the fallback character
     * to prevent them from creating extra path segments.
     *
     * @param array<string, mixed> $record
     * @param array<string, mixed> $fieldConfig
     *
     * @return array<string, mixed>
     */
    private function sanitizeSourceFieldValues(array $record, array $fieldConfig): array
    {
        $fallbackCharacter = (string)($fieldConfig['generatorOptions']['fallbackCharacter'] ?? '-');
        $sourceFields = $fieldConfig['generatorOptions']['fields'] ?? [];

        foreach ($sourceFields as $fieldNameParts) {
            if (is_string($fieldNameParts)) {
                $fieldNameParts = array_map('trim', explode(',', $fieldNameParts));
            }
            foreach ($fieldNameParts as $fieldName) {
                if (isset($record[$fieldName]) && is_string($record[$fieldName])) {
                    $record[$fieldName] = str_replace('/', $fallbackCharacter, $record[$fieldName]);
                }
            }
        }

        return $record;
    }

    public function getLastSegment(string $slug): string
    {
        $slug = trim($slug, '/');
        if ($slug === '') {
            return '/';
        }
        $parts = explode('/', $slug);

        return '/' . array_pop($parts);
    }

    public function combineWithParent(string $parentSlug, string $childSlug): string
    {
        $parentSlug = rtrim($parentSlug, '/');
        $childSegment = $this->getLastSegment($childSlug);

        if ($parentSlug === '' || $parentSlug === '/') {
            return $childSegment;
        }

        return $parentSlug . $childSegment;
    }

    public function getParentSlug(int $pageId, int $languageId = 0): string
    {
        $pageUid = $pageId;

        if ($languageId > 0) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('pages');
            $translatedUid = $queryBuilder->select('uid')
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->eq('l10n_parent', $pageId),
                    $queryBuilder->expr()->eq('sys_language_uid', $languageId)
                )->executeQuery()->fetchOne();
            if ($translatedUid) {
                $pageUid = (int)$translatedUid;
            }
        }

        $record = BackendUtility::getRecordWSOL('pages', $pageUid, 'slug');
        $slug = (string)($record['slug'] ?? '');

        return $slug === '/' ? '' : $slug;
    }

    private function getSlugHelper(): SlugHelper
    {
        $fieldConfig = $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? [];

        return GeneralUtility::makeInstance(
            SlugHelper::class,
            'pages',
            'slug',
            $fieldConfig
        );
    }
}
