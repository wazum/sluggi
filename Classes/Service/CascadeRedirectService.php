<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Redirects\RedirectUpdate\SlugRedirectChangeItem;
use TYPO3\CMS\Redirects\Service\SlugService;

/**
 * Exposes core's redirect creation without its subpage cascade, which sluggi
 * performs itself with its own lock, sync and page-type rules. Calling
 * rebuildSlugsForSlugChange() per descendant instead would re-walk every
 * subtree and create redirects for the intermediate slugs it produces.
 */
final class CascadeRedirectService extends SlugService
{
    public function createRedirectsForSlugChange(
        SlugRedirectChangeItem $changeItem,
        CorrelationId $correlationId,
    ): void {
        $this->initializeSettings($changeItem->getSite());
        if (!$this->autoCreateRedirects) {
            return;
        }

        $this->createCorrelationIds($changeItem->getDefaultLanguagePageId(), $correlationId);

        // Each redirect is persisted through the DataHandler, so core's
        // clearCachePostProc hook refreshes the redirect cache for us — no
        // explicit rebuildForHost() needed here.
        $this->createRedirects(
            $changeItem,
            $changeItem->getDefaultLanguagePageId(),
            (int)($changeItem->getChanged()['sys_language_uid'] ?? 0),
        );
    }
}
