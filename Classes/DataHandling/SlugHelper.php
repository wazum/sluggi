<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandling;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\SlugHelper as CoreSlugHelper;
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
}
