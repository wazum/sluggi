<?php

declare(strict_types=1);

namespace Wazum\Sluggi\DataHandler;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Redirects\Service\SlugService;
use Wazum\Sluggi\Service\MasiCompatibilityService;
use Wazum\Sluggi\Service\SlugCascadeService;

final class HandleMasiExclusionChange
{
    /**
     * @var array<int, array{previous: bool, submitted: bool}>
     */
    private array $capturedValues = [];

    public function __construct(
        private readonly MasiCompatibilityService $masiService,
        private readonly SlugCascadeService $cascadeService,
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

        $correlationId = $correlationId
            ->withSubject(md5('pages:' . $id))
            ->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'slug');

        $updated = 0;
        $skipped = 0;
        $this->cascadeService->cascadeFromPage((int)$id, $correlationId, $updated, $skipped);
    }
}
