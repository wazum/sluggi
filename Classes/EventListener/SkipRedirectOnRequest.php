<?php

declare(strict_types=1);

namespace Wazum\Sluggi\EventListener;

use TYPO3\CMS\Redirects\Event\SlugRedirectChangeItemCreatedEvent;
use TYPO3\CMS\Redirects\RedirectUpdate\RedirectSourceCollection;

final readonly class SkipRedirectOnRequest
{
    public function __invoke(SlugRedirectChangeItemCreatedEvent $event): void
    {
        $changeItem = $event->getSlugRedirectChangeItem();
        $pageId = $changeItem->getPageId();

        if (!$this->shouldCreateRedirect($pageId)) {
            $event->setSlugRedirectChangeItem(
                $changeItem->withSourcesCollection(new RedirectSourceCollection())
            );
        }
    }

    private function shouldCreateRedirect(int $pageId): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $body = $request?->getParsedBody() ?? [];
        $data = $body['data']['pages'] ?? [];

        if (isset($data[$pageId]['tx_sluggi_redirect'])) {
            return (bool)$data[$pageId]['tx_sluggi_redirect'];
        }

        foreach ($data as $pageData) {
            if (isset($pageData['tx_sluggi_redirect']) && (int)$pageData['tx_sluggi_redirect'] === 0) {
                return false;
            }
        }

        return true;
    }
}
