<?php

declare(strict_types=1);

namespace Wazum\Sluggi\EventListener;

use TYPO3\CMS\Redirects\Event\SlugRedirectChangeItemCreatedEvent;
use TYPO3\CMS\Redirects\RedirectUpdate\RedirectSourceCollection;

final readonly class SuppressRedirectForUnpublishedPage
{
    public function __invoke(SlugRedirectChangeItemCreatedEvent $event): void
    {
        $changeItem = $event->getSlugRedirectChangeItem();
        $hidden = (int)($changeItem->getOriginal()['hidden'] ?? 0);
        if ($hidden !== 1) {
            return;
        }

        $event->setSlugRedirectChangeItem(
            $changeItem->withSourcesCollection(new RedirectSourceCollection()),
        );
    }
}
