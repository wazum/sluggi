<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use InvalidArgumentException;
use TYPO3\CMS\Backend\Domain\Repository\Localization\LocalizationRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final readonly class PageTranslationResolver
{
    /**
     * @return array<string, mixed>|null The translation to use, or null to stay on the default language record
     */
    public function resolve(int $pageId, int $languageId, int $workspaceId): ?array
    {
        if ($languageId <= 0) {
            return null;
        }

        foreach ($this->resolveLanguageChain($pageId, $languageId) as $candidateLanguageId) {
            $translation = $this->findTranslation($pageId, $candidateLanguageId, $workspaceId);
            if ($translation !== null) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function resolveLanguageChain(int $pageId, int $languageId): array
    {
        try {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageId);

            return array_values(
                [$languageId, ...$site->getLanguageById($languageId)->getFallbackLanguageIds()]
            );
        } catch (SiteNotFoundException|InvalidArgumentException) {
            return [$languageId];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findTranslation(int $pageId, int $languageId, int $workspaceId): ?array
    {
        if (Typo3Compatibility::getMajorVersion() >= 14) {
            $pageTranslations = GeneralUtility::makeInstance(LocalizationRepository::class)
                ->getPageTranslations($pageId, [$languageId], $workspaceId);
            if ($pageTranslations === []) {
                return null;
            }

            return reset($pageTranslations)->toArray();
        }

        $localizedRecords = BackendUtility::getRecordLocalization('pages', $pageId, $languageId);
        if (empty($localizedRecords)) {
            return null;
        }

        $localizedRecord = reset($localizedRecords);
        BackendUtility::workspaceOL('pages', $localizedRecord, $workspaceId);

        return is_array($localizedRecord) ? $localizedRecord : null;
    }
}
