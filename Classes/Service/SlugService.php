<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use TYPO3\CMS\Redirects\RedirectUpdate\SlugRedirectChangeItem;

final class SlugService extends \TYPO3\CMS\Redirects\Service\SlugService
{
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
