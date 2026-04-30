<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Redirects\Service\SlugService;
use Wazum\Sluggi\Service\SlugCascadeService;

final readonly class RecursiveSlugUpdateController
{
    public function __construct(
        private SlugCascadeService $slugCascadeService,
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

        return new JsonResponse([
            'success' => true,
            'updated' => $updated,
            'skipped' => $skipped,
            'correlations' => [
                'correlationIdSlugUpdate' => (string)$correlationIdSlugUpdate,
                'correlationIdRedirectCreation' => (string)$correlationIdRedirectCreation,
            ],
        ]);
    }
}
