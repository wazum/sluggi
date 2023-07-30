<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

final class SlugService extends \TYPO3\CMS\Redirects\Service\SlugService
{
    protected function checkSubPages(array $currentPageRecord, string $oldSlugOfParentPage, string $newSlugOfParentPage): void
    {
        $languageUid = (int) $currentPageRecord['sys_language_uid'];
        // resolveSubPages needs the page id of the default language
        $pageId = 0 === $languageUid ? (int) $currentPageRecord['uid'] : (int) $currentPageRecord['l10n_parent'];
        $subPageRecords = $this->resolveSubPages($pageId, $languageUid);
        foreach ($subPageRecords as $subPageRecord) {
            if ($subPageRecord['slug_locked']) {
                continue;
            }

            $newSlug = $this->updateSlug($subPageRecord, $oldSlugOfParentPage, $newSlugOfParentPage);
            if (null !== $newSlug && $this->autoCreateRedirects) {
                $subPageId = 0 === (int) $subPageRecord['sys_language_uid'] ? (int) $subPageRecord['uid'] : (int) $subPageRecord['l10n_parent'];
                $this->createRedirect($subPageRecord['slug'], $subPageId, $languageUid, $pageId);
            }
        }
    }
}
