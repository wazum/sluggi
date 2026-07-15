<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Service\SlugChangeReportStore;

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
        // TYPO3 13.4.33/14.3.5 send the ids in the POST body, older cores in
        // the query string.
        $parsedBody = $request->getParsedBody();
        $correlationIds = is_array($parsedBody) ? ($parsedBody['correlation_ids'] ?? null) : null;
        $correlationIds ??= $request->getQueryParams()['correlation_ids'] ?? [];
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

        // The rollback re-applies each historic slug change via DataHandler,
        // which triggers CollectSlugChangeReport just like a regular save. The
        // editor clicked "Revert update" — they expect the JSON success message
        // (rendered by Notification.success in redirect-notification-handler.ts)
        // and nothing else, not a stale "URL paths updated" toast on the next
        // page render. Drop whatever the rollback accumulated.
        GeneralUtility::makeInstance(SlugChangeReportStore::class)->discard();

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
