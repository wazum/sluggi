<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Redirects\Service\SlugService;
use Wazum\Sluggi\Service\SlugCascadeService;
use Wazum\Sluggi\Service\SlugChangeReportStore;

final readonly class RecursiveSlugUpdateController
{
    public function __construct(
        private SlugCascadeService $slugCascadeService,
        private SlugChangeReportStore $reportStore,
    ) {
    }

    public function updateAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!$GLOBALS['BE_USER']->isAdmin()) {
            return new JsonResponse(['success' => false, 'message' => 'Admin access required'], 403);
        }

        $pageId = (int)($request->getQueryParams()['id'] ?? 0);
        if ($pageId <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid page ID'], 400);
        }

        $subject = md5('sluggi-recursive:' . $pageId . ':' . time());
        $baseCorrelationId = CorrelationId::forSubject($subject);
        $correlationIdSlugUpdate = $baseCorrelationId->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'slug');
        $correlationIdRedirectCreation = $baseCorrelationId->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'redirect');

        $updated = 0;
        $skipped = 0;

        try {
            $this->slugCascadeService->cascadeFromPage($pageId, $correlationIdSlugUpdate, $updated, $skipped);
        } catch (Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }

        // The cascade re-enters DataHandler for each descendant, so
        // CollectSlugChangeReport may have accumulated a server-side report.
        // The client emits a synthetic event from the AJAX response so the
        // server-side dispatch on next page render would be a duplicate.
        $this->reportStore->discard();

        $page = BackendUtility::getRecord('pages', $pageId, 'title');
        $title = is_array($page) ? (string)($page['title'] ?? '') : '';

        return new JsonResponse([
            'success' => true,
            'updated' => $updated,
            'skipped' => $skipped,
            'title' => $title,
            'correlations' => [
                'correlationIdSlugUpdate' => (string)$correlationIdSlugUpdate,
                'correlationIdRedirectCreation' => (string)$correlationIdRedirectCreation,
            ],
        ]);
    }
}
