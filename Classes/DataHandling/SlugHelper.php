<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandling;

use InvalidArgumentException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\SlugHelper as CoreSlugHelper;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Slug\SlugNormalizer;

class SlugHelper extends CoreSlugHelper
{
    protected SlugNormalizer $sluggiNormalizer;

    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(string $tableName, string $fieldName, array $configuration, int $workspaceId = 0)
    {
        $this->tableName = $tableName;
        $this->fieldName = $fieldName;
        $this->configuration = $configuration;
        $this->workspaceId = $workspaceId;

        if ($this->tableName === 'pages' && $this->fieldName === 'slug') {
            $this->prependSlashInSlug = true;
        } else {
            $this->prependSlashInSlug = $this->configuration['prependSlash'] ?? false;
        }

        $this->workspaceEnabled = BackendUtility::isTableWorkspaceEnabled($tableName);
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

            foreach ($languageIds as $languageId) {
                $localizedParentPageRecord = BackendUtility::getRecordLocalization(
                    'pages',
                    $parentPageRecord['uid'],
                    $languageId
                );
                if (!empty($localizedParentPageRecord)) {
                    $parentPageRecord = reset($localizedParentPageRecord);
                    break;
                }
            }
        }

        return $parentPageRecord;
    }
}
