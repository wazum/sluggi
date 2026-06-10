<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandling;

use InvalidArgumentException;
use TYPO3\CMS\Backend\Domain\Repository\Localization\LocalizationRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\SlugHelper as CoreSlugHelper;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;
use Wazum\Sluggi\Slug\SlugNormalizer;

class SlugHelper extends CoreSlugHelper
{
    protected SlugNormalizer $sluggiNormalizer;

    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(string $tableName, string $fieldName, array $configuration, int $workspaceId = 0)
    {
        parent::__construct($tableName, $fieldName, $configuration, $workspaceId);
        $this->sluggiNormalizer = GeneralUtility::makeInstance(SlugNormalizer::class);
    }

    public function sanitize(string $slug): string
    {
        $value = $this->sluggiNormalizer->normalize($slug, $this->configuration['fallbackCharacter'] ?? '-');
        if (($value[0] ?? '') !== '/' && $this->prependSlashInSlug) {
            $value = '/' . $value;
        }

        return $value;
    }

    /**
     * Override core's hardcoded exclusion of Spacer (199) and Sysfolder (254)
     * with the configurable exclude_doktypes setting.
     *
     * @return array<string, mixed>|null
     */
    protected function resolveParentPageRecord(int $pid, int $languageId): ?array
    {
        $excludeConfig = (string)($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] ?? '');
        $excludeDokTypes = $excludeConfig !== ''
            ? array_map(intval(...), array_filter(explode(',', $excludeConfig)))
            : [];

        $rootLine = BackendUtility::BEgetRootLine($pid, '', true, ['nav_title']);
        do {
            $parentPageRecord = array_shift($rootLine);
        } while (!empty($rootLine) && in_array((int)$parentPageRecord['doktype'], $excludeDokTypes, true));

        if ($languageId > 0) {
            $languageIds = [$languageId];
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

            try {
                $site = $siteFinder->getSiteByPageId($pid);
                $siteLanguage = $site->getLanguageById($languageId);
                $languageIds = array_merge($languageIds, $siteLanguage->getFallbackLanguageIds());
            } catch (SiteNotFoundException|InvalidArgumentException) {
            }

            foreach ($languageIds as $fallbackLanguageId) {
                $localizedParentPageRecord = $this->resolveLocalizedParentPageRecord(
                    (int)$parentPageRecord['uid'],
                    $fallbackLanguageId
                );
                if ($localizedParentPageRecord !== null) {
                    $parentPageRecord = $localizedParentPageRecord;
                    break;
                }
            }
        }

        return $parentPageRecord;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveLocalizedParentPageRecord(int $pageId, int $languageId): ?array
    {
        if (Typo3Compatibility::getMajorVersion() >= 14) {
            $pageTranslations = GeneralUtility::makeInstance(LocalizationRepository::class)
                ->getPageTranslations($pageId, [$languageId], $this->workspaceId);
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
        BackendUtility::workspaceOL('pages', $localizedRecord, $this->workspaceId);

        return is_array($localizedRecord) ? $localizedRecord : null;
    }
}
