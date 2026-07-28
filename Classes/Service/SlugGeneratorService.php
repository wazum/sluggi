<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\Model\RecordState;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\DataHandling\SlugHelper as SluggiSlugHelper;
use Wazum\Sluggi\Utility\SlugUtility;

final readonly class SlugGeneratorService
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        private MasiCompatibilityService $masiCompatibilityService,
        private PageTranslationResolver $pageTranslationResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $record
     */
    public function generate(array $record, int $pid): string
    {
        return $this->generateForTable($record, $pid, 'pages', 'slug');
    }

    /**
     * @param array<string, mixed> $record
     */
    public function generateForTable(array $record, int $pid, string $table, string $slugField): string
    {
        $fieldConfig = $GLOBALS['TCA'][$table]['columns'][$slugField]['config'] ?? [];
        $slugHelper = $this->getSlugHelperForTable($table, $slugField, $fieldConfig);

        $state = RecordStateFactory::forName($table)
            ->fromArray($record, $pid, $record['uid'] ?? 0);
        $slug = $slugHelper->generate($record, $pid);

        $prependSlash = (bool)($fieldConfig['prependSlash'] ?? ($table === 'pages'));
        $eval = (string)($fieldConfig['eval'] ?? '');

        $uniqueSlug = match ($eval) {
            'unique' => $slugHelper->buildSlugForUniqueInTable($slug, $state),
            'uniqueInPid' => $slugHelper->buildSlugForUniqueInPid($slug, $state),
            default => $this->buildSlugForUniqueInSiteOrFallback($slugHelper, $slug, $state),
        };

        return $prependSlash ? '/' . ltrim($uniqueSlug, '/') : $uniqueSlug;
    }

    /**
     * @param array<string, mixed>|null $record Optional record for postModifier context
     */
    public function combineWithParent(
        string $parentSlug,
        string $childSlug,
        ?array $record = null,
        ?int $pid = null,
    ): string {
        $parentSlug = rtrim($parentSlug, '/');
        $childSegment = '/' . SlugUtility::getLastSegment($childSlug);

        if ($parentSlug === '' || $parentSlug === '/') {
            $slug = $childSegment;
        } else {
            $slug = $parentSlug . $childSegment;
        }

        if ($record !== null) {
            $slug = $this->applyPostModifiers($slug, $record, $pid ?? 0);
        }

        return $slug;
    }

    /**
     * Re-prefixes an existing slug, keeping the page's own segment. Post
     * modifiers may still rewrite the prefix.
     *
     * @param array<string, mixed> $record
     */
    public function reparentSlug(string $parentSlug, string $childSlug, array $record, int $pid): string
    {
        $slug = $this->combineWithParent($parentSlug, $childSlug, $record, $pid);
        $childSegment = '/' . SlugUtility::getLastSegment($childSlug);

        if ($childSegment === '/' || '/' . SlugUtility::getLastSegment($slug) === $childSegment) {
            return $slug;
        }

        return SlugUtility::enforceParentPath(SlugUtility::getParentPath($slug), $childSegment);
    }

    /**
     * Get the parent slug, skipping excluded page types (sysfolders, spacers, etc.).
     * This ensures children of excluded pages get correct URL prefixes.
     */
    public function getParentSlug(int $pageId, int $languageId = 0): string
    {
        $rootLine = BackendUtility::BEgetRootLine(
            $pageId,
            '',
            true,
            $this->masiCompatibilityService->getAdditionalRootLineFields()
        );

        foreach ($rootLine as $page) {
            if ($this->extensionConfiguration->isPageTypeExcluded((int)($page['doktype'] ?? 1))) {
                continue;
            }

            // masi drops such a page's segment from every descendant path, so
            // the effective prefix comes from further up the rootline.
            if ($this->masiCompatibilityService->excludesSlugForSubpages($page)) {
                continue;
            }

            return $this->getSlugForPage((int)$page['uid'], $languageId);
        }

        return '';
    }

    private function getSlugForPage(int $pageId, int $languageId): string
    {
        $translation = $this->pageTranslationResolver->resolve($pageId, $languageId, $this->getWorkspaceId());
        $pageUid = $translation !== null ? (int)$translation['uid'] : $pageId;

        // Re-read rather than trusting the resolved row: BEgetRootLine() results
        // are held in a runtime cache, and a cascade rewrites slugs mid-request.
        $record = BackendUtility::getRecordWSOL('pages', $pageUid, 'slug');
        $slug = (string)($record['slug'] ?? '');

        return $slug === '/' ? '' : $slug;
    }

    /**
     * @param array<string, mixed> $record
     */
    public function ensureUnique(
        string $slug,
        array $record,
        int $pid,
        int $uid,
        string $table = 'pages',
        string $slugField = 'slug',
    ): string {
        $fieldConfig = $GLOBALS['TCA'][$table]['columns'][$slugField]['config'] ?? [];
        $slugHelper = $this->getSlugHelperForTable($table, $slugField, $fieldConfig);
        $state = RecordStateFactory::forName($table)->fromArray($record, $pid, $uid);
        $eval = (string)($fieldConfig['eval'] ?? '');

        return match ($eval) {
            'unique' => $slugHelper->buildSlugForUniqueInTable($slug, $state),
            'uniqueInPid' => $slugHelper->buildSlugForUniqueInPid($slug, $state),
            default => $this->buildSlugForUniqueInSiteOrFallback($slugHelper, $slug, $state),
        };
    }

    private function buildSlugForUniqueInSiteOrFallback(
        SlugHelper $slugHelper,
        string $slug,
        RecordState $state,
    ): string {
        try {
            return $slugHelper->buildSlugForUniqueInSite($slug, $state);
        } catch (SiteNotFoundException) {
            return $slug;
        }
    }

    /**
     * @param array<string, mixed> $record
     */
    private function applyPostModifiers(string $slug, array $record, int $pid): string
    {
        $fieldConfig = SluggiSlugHelper::applyDefaultReplacements(
            $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? []
        );
        $postModifiers = $fieldConfig['generatorOptions']['postModifiers'] ?? [];

        if ($postModifiers === []) {
            return $slug;
        }

        // TYPO3 core calls postModifiers without leading slash
        $slug = ltrim($slug, '/');

        $slugHelper = $this->getSlugHelper();
        $prefix = SlugUtility::getParentPath($slug);

        foreach ($postModifiers as $funcName) {
            $hookParameters = [
                'slug' => $slug,
                'workspaceId' => $this->getWorkspaceId(),
                'configuration' => $fieldConfig,
                'record' => $record,
                'pid' => $pid,
                'prefix' => $prefix,
                'tableName' => 'pages',
                'fieldName' => 'slug',
            ];
            $slug = GeneralUtility::callUserFunction($funcName, $hookParameters, $slugHelper);
        }

        return $this->sanitizeSlug($slug);
    }

    private function sanitizeSlug(string $slug): string
    {
        return $this->getSlugHelper()->sanitize($slug);
    }

    private function getSlugHelper(): SlugHelper
    {
        return $this->getSlugHelperForTable('pages', 'slug', $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? []);
    }

    /**
     * @param array<string, mixed> $fieldConfig
     */
    private function getSlugHelperForTable(string $table, string $slugField, array $fieldConfig): SlugHelper
    {
        return GeneralUtility::makeInstance(
            SlugHelper::class,
            $table,
            $slugField,
            $fieldConfig,
            $this->getWorkspaceId()
        );
    }

    private function getWorkspaceId(): int
    {
        // @phpstan-ignore nullsafe.neverNull
        return $this->getBackendUser()?->workspace ?? 0;
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
