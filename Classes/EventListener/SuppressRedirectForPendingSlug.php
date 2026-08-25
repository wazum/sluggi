<?php

declare(strict_types=1);

namespace Wazum\Sluggi\EventListener;

use TYPO3\CMS\Redirects\Event\SlugRedirectChangeItemCreatedEvent;
use TYPO3\CMS\Redirects\RedirectUpdate\RedirectSourceCollection;

final readonly class SuppressRedirectForPendingSlug
{
    public function __invoke(SlugRedirectChangeItemCreatedEvent $event): void
    {
        $changeItem = $event->getSlugRedirectChangeItem();
        if (!($changeItem->getOriginal()['tx_sluggi_slug_pending'] ?? false)) {
            return;
        }

        // The path being replaced is the one core derived from the "Translate to …"
        // placeholder. Nobody ever published or linked it, so a redirect from it is
        // noise for every translated page.
        $event->setSlugRedirectChangeItem(
            $changeItem->withSourcesCollection(new RedirectSourceCollection()),
        );
    }
}
