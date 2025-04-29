<?php

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Redirects\RedirectUpdate\SlugRedirectChangeItemFactory;
use Wazum\Sluggi\Helper\Configuration;
use Wazum\Sluggi\Helper\PermissionHelper;
use Wazum\Sluggi\Helper\SlugHelper as SluggiSlugHelper;
use Wazum\Sluggi\Service\SlugService;

final class HandlePageUpdate implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var array<int, bool>
     */
    private array $processedSlugForPage;

    public function __construct(
        private readonly SlugService $slugService,
        private readonly SlugRedirectChangeItemFactory $slugRedirectChangeItemFactory
    ) {
    }

    /**
     * @param array<array-key, mixed> $fields
     */
    public function processDatamap_preProcessFieldArray(
        array &$fields,
        string $table,
        string|int $id,
        DataHandler $dataHandler
    ): void {
        if (!$this->shouldRun($table, $id, $fields, $dataHandler)) {
            return;
        }

        if (!empty($fields['slug'])) {
            $this->processedSlugForPage[(int) $id] = true;
            $fields['slug'] = $this->sanitizeSlug($fields['slug']);
        }

        $pageRecord = BackendUtility::getRecordWSOL($table, (int) $id);
        if (null === $pageRecord) {
            /* @psalm-suppress PossiblyNullReference */
            $this->logger->warning(sprintf('Unable to get page record with ID "%s"', $id));

            return;
        }

        // If the slug is locked and the user has no access to the lock field, no update is allowed
        if (PermissionHelper::isLocked($pageRecord) && !PermissionHelper::hasSlugLockAccess($pageRecord)) {
            unset($fields['slug']);

            return;
        }

        // Synchronize is the easiest case, as we just have to regenerate the slug from the page data
        if ($this->shouldSynchronize($pageRecord, $fields)) {
            $fields = $this->synchronize($pageRecord, $fields);

            return;
        }

        if ($this->isManualUpdateWithOnlyLastSegmentAllowed($fields)) {
            $fields = $this->updateLastSegment($pageRecord, $fields);

            return;
        }

        if (!empty($fields['slug'])) {
            $fields = $this->prependInaccessibleSlugSegments($pageRecord, $fields);
        }
    }

    public function processDatamap_postProcessFieldArray(
        string $status,
        string $table,
        string|int $id,
        array &$fields,
        DataHandler $dataHandler
    ): void {
        if (
            'pages' !== $table
            || 'update' !== $status
            || $this->isNestedHookInvocation($dataHandler)
        ) {
            return;
        }

        // We have to double-check again here,
        // as the slug is empty e.g. if the title has changed via inline editing in page tree
        if (isset($this->processedSlugForPage[(int) $id])) {
            return;
        }

        $pageRecord = BackendUtility::getRecordWSOL($table, (int) $id);
        if (null === $pageRecord) {
            /* @psalm-suppress PossiblyNullReference */
            $this->logger->warning(sprintf('Unable to get page pageRecord with ID "%s"', $id));

            return;
        }

        if ($this->shouldSynchronize($pageRecord, $fields)) {
            $fields = $this->synchronize($pageRecord, $fields);

            if (!empty($fields['slug']) && $fields['slug'] !== $pageRecord['slug']) {
                $changeItem = $this->slugRedirectChangeItemFactory->create($pageRecord['uid'])
                    ?->withChanged(array_merge($pageRecord, $fields));
                if ($changeItem) {
                    $this->slugService->rebuildSlugsForSlugChange(
                        $id,
                        $changeItem,
                        $dataHandler->getCorrelationId()
                    );
                }
            }
        }
    }

    /**
     * @param array<array-key, mixed> $pageRecord
     * @param array<array-key, mixed> $fields
     *
     * @return array<array-key, mixed>
     */
    private function synchronize(array $pageRecord, array $fields): array
    {
        try {
            $fields['slug'] = $this->regenerateSlug(
                $this->mergePageRecordWithIncomingFields($pageRecord, $fields)
            );
        } catch (\Throwable) {
            // Ignore
        }

        return $fields;
    }

    /**
     * @param array<array-key, mixed> $pageRecord
     * @param array<array-key, mixed> $fields
     *
     * @return array<array-key, mixed>
     */
    private function mergePageRecordWithIncomingFields(array $pageRecord, array $fields): array
    {
        if (isset($fields['slug'])) {
            $fields['slug'] = $this->sanitizeSlug($fields['slug']);
        }

        return array_merge($pageRecord, $fields);
    }

    /**
     * @param array<array-key, mixed> $pageRecord
     *
     * @throws SiteNotFoundException
     */
    private function regenerateSlug(array $pageRecord): string
    {
        /** @var SlugHelper $helper */
        $helper = GeneralUtility::makeInstance(
            SlugHelper::class,
            'pages',
            'slug',
            $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? []
        );

        $state = RecordStateFactory::forName('pages')->fromArray($pageRecord, $pageRecord['pid'], $pageRecord['uid']);
        $slug = $helper->generate($pageRecord, (int) $pageRecord['pid']);
        $newSlug = $helper->buildSlugForUniqueInSite($slug, $state);

        return $this->sanitizeSlug($newSlug);
    }

    /**
     * @param array<array-key, mixed> $fields
     *
     * @psalm-suppress InternalMethod
     */
    private function shouldRun(
        string $table,
        string|int $id,
        array $fields,
        DataHandler $dataHandler
    ): bool {
        return
            'pages' === $table
            && MathUtility::canBeInterpretedAsInteger($id)
            && !$this->isExcludedPageType($id, $fields)
            // This is set in \TYPO3\CMS\Backend\History\RecordHistoryRollback::performRollback,
            // so we use it as a flag to ignore the update
            && !$dataHandler->dontProcessTransformations
            && !$this->isNestedHookInvocation($dataHandler)
            && $dataHandler->checkRecordUpdateAccess($table, (int) $id, $fields);
    }

    /**
     * @param array<array-key, mixed> $pageRecord
     * @param array<array-key, mixed> $fields
     */
    private function shouldSynchronize(array $pageRecord, array $fields): bool
    {
        if (false === $this->isSynchronizationActiveInExtensionConfiguration()) {
            return false;
        }

        $locked = (bool) ($pageRecord['slug_locked'] ?? false);
        if (isset($fields['slug_locked'])) {
            $locked = (bool) $fields['slug_locked'];
        }
        if ($locked) {
            return false;
        }

        if (!$this->isSynchronizationActiveForPage($pageRecord, $fields)) {
            return false;
        }

        return $this->hasSlugRelevantFieldsChanged($pageRecord, $fields);
    }

    /**
     * @param array<array-key, mixed> $fields
     */
    private function isExcludedPageType(string|int $id, array $fields): bool
    {
        if (!isset($fields['doktype'])) {
            $pageRecord = BackendUtility::getRecordWSOL('pages', (int) $id, 'doktype');
            $pageType = (int) $pageRecord['doktype'];
        } else {
            $pageType = (int) $fields['doktype'];
        }

        return in_array(
            $pageType,
            GeneralUtility::intExplode(',', Configuration::get('exclude_page_types') ?? '', true), true
        );
    }

    /**
     * Determines whether our identifier is part of correlation id aspects.
     * In that case it would be a nested call which has to be ignored.
     *
     * @psalm-suppress UndefinedClass
     * @psalm-suppress InternalMethod
     */
    private function isNestedHookInvocation(DataHandler $dataHandler): bool
    {
        if (!ExtensionManagementUtility::isLoaded('redirects')) {
            return false;
        }

        $correlationId = $dataHandler->getCorrelationId();
        $correlationIdAspects = $correlationId ? $correlationId->getAspects() : [];

        return in_array(
            SlugService::CORRELATION_ID_IDENTIFIER,
            $correlationIdAspects,
            true
        );
    }

    private function isSynchronizationActiveInExtensionConfiguration(): bool
    {
        return (bool) Configuration::get('synchronize');
    }

    /**
     * @param array<array-key, mixed> $pageRecord
     * @param array<array-key, mixed> $fields
     */
    private function isSynchronizationActiveForPage(array $pageRecord, array $fields): bool
    {
        $fields = $this->mergePageRecordWithIncomingFields($pageRecord, $fields);

        return false !== (bool) $fields['tx_sluggi_sync'];
    }

    private function sanitizeSlug(string $slug): string
    {
        $slug = preg_replace('#/+#', '/', $slug);

        return '/' . ltrim($slug, '/');
    }

    private function isManualUpdateWithOnlyLastSegmentAllowed(array $fields): bool
    {
        return isset($fields['slug']) && Configuration::get('last_segment_only') && !PermissionHelper::hasFullPermission();
    }

    /**
     * @param array<array-key, mixed> $pageRecord
     * @param array<array-key, mixed> $fields
     *
     * @return array<array-key, mixed>
     */
    private function updateLastSegment(array $pageRecord, array $fields): array
    {
        /** @var SlugHelper $helper */
        $helper = GeneralUtility::makeInstance(
            SlugHelper::class,
            'pages',
            'slug',
            $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? []
        );

        // Slashes are not allowed here
        $segment = ltrim($fields['slug'], '/');
        $fields['slug'] = '/' . str_replace('/', '-', $segment);

        // Only exchange the last segment
        $parts = \explode('/', $pageRecord['slug']);
        array_pop($parts);
        $fields['slug'] = implode('/', $parts) . $helper->sanitize($fields['slug']);

        return $fields;
    }

    /**
     * @param array<array-key, mixed> $pageRecord
     * @param array<array-key, mixed> $fields
     *
     * @return array<array-key, mixed>
     */
    private function prependInaccessibleSlugSegments(array $pageRecord, array $fields): array
    {
        $languageId = $pageRecord['sys_language_uid'];
        $mountRootPage = PermissionHelper::getTopmostAccessiblePage($pageRecord['uid']);
        $inaccessibleSlugSegments = null;
        if (null !== $mountRootPage) {
            $inaccessibleSlugSegments = SluggiSlugHelper::getSlug($mountRootPage['pid'], $languageId);
        }
        if (!empty($inaccessibleSlugSegments)) {
            $fields['slug'] = $inaccessibleSlugSegments . '/' . ltrim($fields['slug'], '/');
        }

        return $fields;
    }

    /**
     * @param array<array-key, mixed> $pageRecord
     * @param array<array-key, mixed> $fields
     */
    private function hasSlugRelevantFieldsChanged(array $pageRecord, array $fields): bool
    {
        $relevantFields = json_decode(Configuration::get('pages_fields') ?? '[]', true);
        if (!is_array($relevantFields) || empty($relevantFields)) {
            return false;
        }

        $slugFields = array_flatten($relevantFields);
        foreach ($slugFields as $field) {
            if (isset($fields[$field]) && $fields[$field] !== ($pageRecord[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
