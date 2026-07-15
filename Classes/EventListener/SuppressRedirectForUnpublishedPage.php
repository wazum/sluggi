<?php

declare(strict_types=1);

namespace Wazum\Sluggi\EventListener;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Redirects\Event\SlugRedirectChangeItemCreatedEvent;
use TYPO3\CMS\Redirects\RedirectUpdate\RedirectSourceCollection;

final readonly class SuppressRedirectForUnpublishedPage
{
    public function __construct(
        private Context $context,
    ) {
    }

    public function __invoke(SlugRedirectChangeItemCreatedEvent $event): void
    {
        $changeItem = $event->getSlugRedirectChangeItem();
        if (!$this->isUnpublished($changeItem->getOriginal())) {
            return;
        }

        $event->setSlugRedirectChangeItem(
            $changeItem->withSourcesCollection(new RedirectSourceCollection()),
        );
    }

    /**
     * @param array<string, mixed> $record
     */
    private function isUnpublished(array $record): bool
    {
        if ((int)($record['hidden'] ?? 0) === 1) {
            return true;
        }

        $now = (int)$this->context->getPropertyFromAspect('date', 'timestamp');
        if ((int)($record['starttime'] ?? 0) > $now) {
            return true;
        }

        $endtime = (int)($record['endtime'] ?? 0);

        return $endtime !== 0 && $endtime <= $now;
    }
}
