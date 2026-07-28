<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Redirects\Service\SlugService;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\MasiCompatibilityService;
use Wazum\Sluggi\Service\SlugCascadeService;
use Wazum\Sluggi\Service\SlugChangeReportStore;

final class HandleMasiExclusionChange
{
    /**
     * @var array<int, array{previous: bool, submitted: bool}>
     */
    private array $capturedValues = [];

    /**
     * @var array<int, CorrelationId>
     */
    private array $pendingCascades = [];

    public function __construct(
        private readonly MasiCompatibilityService $masiService,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly SlugCascadeService $cascadeService,
        private readonly SlugChangeReportStore $reportStore,
    ) {
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_preProcessFieldArray(
        array &$fieldArray,
        string $table,
        string|int $id,
        DataHandler $dataHandler,
    ): void {
        if ($table !== 'pages' || !is_numeric($id)) {
            return;
        }
        if (!$this->masiService->isActive()) {
            return;
        }
        if (!$this->masiService->isExclusionFieldSubmitted($fieldArray)) {
            return;
        }
        // Excluded page types are skipped when resolving paths, so the flag
        // changes nothing below them
        if ($this->extensionConfiguration->isPageTypeExcluded($this->resolvePageType($fieldArray, (int)$id))) {
            return;
        }

        $this->capturedValues[(int)$id] = [
            'previous' => $this->masiService->getCurrentExclusionValue((int)$id),
            'submitted' => $this->masiService->getSubmittedExclusionValue($fieldArray),
        ];
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        string|int $id,
        array $fieldArray,
        DataHandler $dataHandler,
    ): void {
        if ($status !== 'update' || $table !== 'pages' || !is_numeric($id)) {
            return;
        }
        if (!isset($this->capturedValues[(int)$id])) {
            return;
        }

        $captured = $this->capturedValues[(int)$id];
        unset($this->capturedValues[(int)$id]);

        if ($captured['previous'] === $captured['submitted']) {
            return;
        }

        $correlationId = $dataHandler->getCorrelationId();
        if ($correlationId === null) {
            return;
        }

        // Deferred: the flag is synchronised into the translation rows as part of
        // this same run, and masi reads it from there
        $this->pendingCascades[(int)$id] = $correlationId->withSubject(md5('pages:' . $id));
    }

    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        $pending = $this->pendingCascades;
        $this->pendingCascades = [];

        foreach ($pending as $pageId => $baseCorrelationId) {
            $record = BackendUtility::getRecordWSOL('pages', $pageId, 'sys_language_uid');
            // The flag is coupled across languages, so a translation carries no
            // decision of its own and the default language run covers it
            if ((int)($record['sys_language_uid'] ?? 0) > 0) {
                continue;
            }

            $updated = 0;
            $skipped = 0;
            $this->cascadeService->cascadeFromPage(
                $pageId,
                $baseCorrelationId->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'slug'),
                $updated,
                $skipped,
            );
            $this->reportCascade($pageId, $baseCorrelationId, $updated);
        }
    }

    private function reportCascade(int $pageId, CorrelationId $baseCorrelationId, int $updated): void
    {
        if ($updated === 0) {
            return;
        }

        $record = BackendUtility::getRecordWSOL('pages', $pageId, 'title');
        $this->reportStore->setCascadeRoot($pageId, (string)($record['title'] ?? ''), [
            'correlationIdSlugUpdate' => (string)$baseCorrelationId->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'slug'),
            'correlationIdRedirectCreation' => (string)$baseCorrelationId->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'redirect'),
        ]);
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    private function resolvePageType(array $fieldArray, int $pageId): int
    {
        if (isset($fieldArray['doktype'])) {
            return (int)$fieldArray['doktype'];
        }

        $record = BackendUtility::getRecordWSOL('pages', $pageId, 'doktype');

        return (int)($record['doktype'] ?? 1);
    }
}
