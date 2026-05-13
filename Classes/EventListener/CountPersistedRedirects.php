<?php

declare(strict_types=1);

namespace Wazum\Sluggi\EventListener;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Redirects\Event\AfterAutoCreateRedirectHasBeenPersistedEvent;
use Wazum\Sluggi\Service\SlugChangeReportStore;

final readonly class CountPersistedRedirects
{
    public function __construct(
        private SlugChangeReportStore $store,
    ) {
    }

    public function __invoke(AfterAutoCreateRedirectHasBeenPersistedEvent $event): void
    {
        $redirectRecord = $event->getRedirectRecord();
        $uid = (int)($redirectRecord['uid'] ?? 0);
        if ($uid === 0) {
            return;
        }
        $row = BackendUtility::getRecord('sys_redirect', $uid, 'uid,deleted,disabled', '', false);
        if (!is_array($row) || (int)($row['deleted'] ?? 0) === 1 || (int)($row['disabled'] ?? 0) === 1) {
            return;
        }
        $beUser = $this->getBackendUser();
        if ($beUser === null) {
            return;
        }
        $this->store->incrementRedirectsCreated($beUser);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        $beUser = $GLOBALS['BE_USER'] ?? null;

        return $beUser instanceof BackendUserAuthentication ? $beUser : null;
    }
}
