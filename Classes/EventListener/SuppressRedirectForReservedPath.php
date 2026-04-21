<?php

declare(strict_types=1);

namespace Wazum\Sluggi\EventListener;

use TYPO3\CMS\Redirects\Event\SlugRedirectChangeItemCreatedEvent;
use TYPO3\CMS\Redirects\RedirectUpdate\RedirectSourceCollection;
use Wazum\Sluggi\Service\ReservedPathService;

final readonly class SuppressRedirectForReservedPath
{
    public function __construct(
        private ReservedPathService $reservedPathService,
    ) {
    }

    public function __invoke(SlugRedirectChangeItemCreatedEvent $event): void
    {
        $changeItem = $event->getSlugRedirectChangeItem();
        $site = $this->reservedPathService->findSiteForPage($changeItem->getPageId());
        if ($site === null) {
            return;
        }

        $patterns = $this->reservedPathService->getReservedPathsForSite($site);
        if ($patterns === []) {
            return;
        }

        $oldSlug = (string)($changeItem->getOriginal()['slug'] ?? '');
        if (!$this->reservedPathService->isReserved($oldSlug, $patterns)) {
            return;
        }

        $event->setSlugRedirectChangeItem(
            $changeItem->withSourcesCollection(new RedirectSourceCollection()),
        );
    }
}
