<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * Fixes "Revert update" to also revert the parent page's own slug change.
 *
 * Core only rolls back correlation IDs with SlugService aspects (child slug updates
 * and redirects), skipping the base correlation ID that represents the parent page change.
 *
 * @see https://forge.typo3.org/issues/108870
 */
trait RecordHistoryRollbackControllerTrait
{
    abstract protected function createLanguageService(): LanguageService;

    public function revertCorrelation(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->createLanguageService();
        $revertedCorrelationTypes = [];
        $correlationIds = $request->getQueryParams()['correlation_ids'] ?? [];
        /** @var CorrelationId[] $correlationIds */
        $correlationIds = array_map(
            static fn (string $correlationId) => CorrelationId::fromString($correlationId),
            $correlationIds,
        );
        foreach ($correlationIds as $correlationId) {
            $type = $correlationId->getAspects()[1] ?? null;
            if ($type !== null) {
                $revertedCorrelationTypes[] = $type;
            }
            $this->rollBackCorrelation($correlationId);
        }
        $result = [
            'status' => 'error',
            'title' => $languageService->sL('LLL:EXT:redirects/Resources/Private/Language/locallang_slug_service.xlf:redirects_error_title'),
            'message' => $languageService->sL('LLL:EXT:redirects/Resources/Private/Language/locallang_slug_service.xlf:redirects_error_message'),
        ];
        if (in_array('redirect', $revertedCorrelationTypes, true)) {
            $result = [
                'status' => 'ok',
                'title' => $languageService->sL('LLL:EXT:redirects/Resources/Private/Language/locallang_slug_service.xlf:revert_redirects_success_title'),
                'message' => $languageService->sL('LLL:EXT:redirects/Resources/Private/Language/locallang_slug_service.xlf:revert_redirects_success_message'),
            ];
            if (in_array('slug', $revertedCorrelationTypes, true)) {
                $result = [
                    'status' => 'ok',
                    'title' => $languageService->sL('LLL:EXT:redirects/Resources/Private/Language/locallang_slug_service.xlf:revert_update_success_title'),
                    'message' => $languageService->sL('LLL:EXT:redirects/Resources/Private/Language/locallang_slug_service.xlf:revert_update_success_message'),
                ];
            }
        }

        return new JsonResponse($result);
    }
}
