<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandling;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\SlugHelper as CoreSlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Service\PageTranslationResolver;
use Wazum\Sluggi\Service\SlugGenerationRecordResolver;
use Wazum\Sluggi\Slug\SlugNormalizer;

class SlugHelper extends CoreSlugHelper
{
    protected SlugNormalizer $sluggiNormalizer;

    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(string $tableName, string $fieldName, array $configuration, int $workspaceId = 0)
    {
        parent::__construct($tableName, $fieldName, self::applyDefaultReplacements($configuration), $workspaceId);
        $this->sluggiNormalizer = GeneralUtility::makeInstance(SlugNormalizer::class);
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    public static function applyDefaultReplacements(array $configuration): array
    {
        // Core treats "/" in source-field values as a path separator during
        // generation; a TCA replacements entry for "/" wins over this default.
        // Callers that pass the configuration to postModifiers themselves must
        // route it through here, or a postModifier sees a different
        // configuration depending on whether core or sluggi invoked it.
        $configuration['generatorOptions']['replacements']['/'] ??= (string)($configuration['fallbackCharacter'] ?? '-');

        return $configuration;
    }

    /**
     * @param array<string, mixed> $recordData
     */
    public function generate(array $recordData, int $pid): string
    {
        return parent::generate(
            GeneralUtility::makeInstance(SlugGenerationRecordResolver::class)->resolve(
                $recordData,
                $this->tableName,
                $this->fieldName,
                $pid,
                $this->workspaceId,
                $this->configuration,
            ),
            $pid
        );
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

        $translation = GeneralUtility::makeInstance(PageTranslationResolver::class)
            ->resolve((int)$parentPageRecord['uid'], $languageId, $this->workspaceId);

        return $translation ?? $parentPageRecord;
    }
}
