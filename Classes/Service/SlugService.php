<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Redirects\RedirectUpdate\SlugRedirectChangeItem;
use TYPO3\CMS\Redirects\RedirectUpdate\SlugRedirectChangeItemFactory;
use TYPO3\CMS\Redirects\Service\RedirectCacheService;

final class SlugService extends \TYPO3\CMS\Redirects\Service\SlugService
{
    public function __construct(
        Context $context,
        PageRepository $pageRepository,
        LinkService $linkService,
        RedirectCacheService $redirectCacheService,
        private readonly SlugRedirectChangeItemFactory $slugRedirectChangeItemFactory,
        EventDispatcherInterface $eventDispatcher,
        ConnectionPool $connectionPool,
    ) {
        parent::__construct(
            $context,
            $pageRepository,
            $linkService,
            $redirectCacheService,
            $slugRedirectChangeItemFactory,
            $eventDispatcher,
            $connectionPool
        );
    }

    protected function checkSubPages(array $currentPageRecord, SlugRedirectChangeItem $parentChangeItem): array
    {
        $sourceHosts = [];
        $languageUid = (int) $currentPageRecord['sys_language_uid'];
        // resolveSubPages needs the page id of the default language
        $pageId = 0 === $languageUid ? (int) $currentPageRecord['uid'] : (int) $currentPageRecord['l10n_parent'];
        $subPageRecords = $this->resolveSubPages($pageId, $languageUid);
        foreach ($subPageRecords as $subPageRecord) {
            if ($subPageRecord['slug_locked']) {
                continue;
            }

            $changeItem = $this->slugRedirectChangeItemFactory->create(
                pageId: (int) $subPageRecord['uid'],
                original: $subPageRecord
            );
            if (null === $changeItem) {
                continue;
            }
            $updatedPageRecord = $this->updateSlug($subPageRecord, $parentChangeItem);
            if (null !== $updatedPageRecord && $this->autoCreateRedirects) {
                $subPageId = 0 === (int) $subPageRecord['sys_language_uid'] ? (int) $subPageRecord['uid'] : (int) $subPageRecord['l10n_parent'];
                $changeItem = $changeItem->withChanged($updatedPageRecord);
                $sourceHosts += array_values($this->createRedirects($changeItem, $subPageId, $languageUid));
            }
        }

        return $sourceHosts;
    }
}
