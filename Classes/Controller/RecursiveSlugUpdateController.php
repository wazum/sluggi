<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Controller;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Redirects\Hooks\DataHandlerSlugUpdateHook;
use TYPO3\CMS\Redirects\Service\SlugService;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\SlugGeneratorService;
use Wazum\Sluggi\Service\SlugLockService;

final readonly class RecursiveSlugUpdateController
{
    private const CORE_HOOK_KEY = 'redirects';

    public function __construct(
        private SlugGeneratorService $slugGeneratorService,
        private SlugLockService $slugLockService,
        private ExtensionConfiguration $extensionConfiguration,
        private ConnectionPool $connectionPool,
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

        $this->disableCoreSlugHook();
        try {
            $this->processChildren($pageId, $correlationIdSlugUpdate, $updated, $skipped);
        } catch (Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        } finally {
            $this->enableCoreSlugHook();
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

    private function processChildren(
        int $parentPageId,
        CorrelationId $correlationId,
        int &$updated,
        int &$skipped,
    ): void {
        foreach ($this->getDirectChildren($parentPageId) as $child) {
            $this->processPage($child, $correlationId, $updated, $skipped);
        }
    }

    /**
     * @param array<string, mixed> $page
     */
    private function processPage(
        array $page,
        CorrelationId $correlationId,
        int &$updated,
        int &$skipped,
    ): void {
        $pageId = (int)$page['uid'];

        if ($this->extensionConfiguration->isPageTypeExcluded((int)$page['doktype'])) {
            ++$skipped;
            $this->processChildren($pageId, $correlationId, $updated, $skipped);

            return;
        }

        if ($this->slugLockService->isLocked($page)) {
            ++$skipped;
            if (!$this->extensionConfiguration->isLockDescendantsEnabled()) {
                $this->processChildren($pageId, $correlationId, $updated, $skipped);
            }

            return;
        }

        $this->regenerateSlug($page, $correlationId, $updated);

        foreach ($this->getTranslations($pageId) as $translation) {
            if ($this->slugLockService->isLocked($translation)) {
                ++$skipped;
                continue;
            }
            $this->regenerateSlug($translation, $correlationId, $updated);
        }

        $this->processChildren($pageId, $correlationId, $updated, $skipped);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function regenerateSlug(array $record, CorrelationId $correlationId, int &$updated): void
    {
        $languageId = (int)($record['sys_language_uid'] ?? 0);
        $parentSlug = $this->slugGeneratorService->getParentSlug((int)$record['pid'], $languageId);
        $generatedSlug = $this->slugGeneratorService->generate($record, (int)$record['pid']);
        $newSlug = $this->slugGeneratorService->combineWithParent(
            $parentSlug,
            $generatedSlug,
            $record,
            (int)$record['pid'],
        );

        if ($newSlug !== $record['slug']) {
            $this->persistSlug((int)$record['uid'], $newSlug, $correlationId);
            ++$updated;
        }
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    private function getDirectChildren(int $parentPageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);

        return $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($parentPageId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
            )
            ->orderBy('uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    private function getTranslations(int $defaultLanguageUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);

        return $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($defaultLanguageUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->gt('sys_language_uid', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
            )
            ->orderBy('sys_language_uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function persistSlug(int $uid, string $newSlug, CorrelationId $correlationId): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['pages' => [$uid => ['slug' => $newSlug]]],
            [],
        );
        $dataHandler->setCorrelationId($correlationId);
        $dataHandler->process_datamap();
    }

    private function disableCoreSlugHook(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][self::CORE_HOOK_KEY]);
    }

    private function enableCoreSlugHook(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][self::CORE_HOOK_KEY] =
            DataHandlerSlugUpdateHook::class;
    }
}
