<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\EventListener;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Redirects\Event\SlugRedirectChangeItemCreatedEvent;
use TYPO3\CMS\Redirects\RedirectUpdate\RedirectSourceCollection;
use TYPO3\CMS\Redirects\RedirectUpdate\SlugRedirectChangeItem;
use Wazum\Sluggi\EventListener\SuppressRedirectForReservedPath;
use Wazum\Sluggi\Service\ReservedPathService;

final class SuppressRedirectForReservedPathTest extends TestCase
{
    #[Test]
    public function clearsSourcesWhenOriginalSlugIsReserved(): void
    {
        $listener = $this->createListener(['/api']);
        $changeItem = $this->createChangeItem(42, '/api');
        $event = new SlugRedirectChangeItemCreatedEvent($changeItem);

        $listener($event);

        self::assertNotSame($changeItem, $event->getSlugRedirectChangeItem(), 'Event should carry a replaced change item');
    }

    #[Test]
    public function leavesSourcesIntactWhenOriginalSlugIsNotReserved(): void
    {
        $listener = $this->createListener(['/api']);
        $changeItem = $this->createChangeItem(42, '/about');
        $event = new SlugRedirectChangeItemCreatedEvent($changeItem);

        $listener($event);

        self::assertSame($changeItem, $event->getSlugRedirectChangeItem());
    }

    #[Test]
    public function skipsWhenNoPatternsConfigured(): void
    {
        $listener = $this->createListener([]);
        $changeItem = $this->createChangeItem(42, '/api');
        $event = new SlugRedirectChangeItemCreatedEvent($changeItem);

        $listener($event);

        self::assertSame($changeItem, $event->getSlugRedirectChangeItem());
    }

    /**
     * @param list<string> $reservedPaths
     */
    private function createListener(array $reservedPaths): SuppressRedirectForReservedPath
    {
        $settings = self::makeSiteSettings(['sluggi' => ['reservedPaths' => $reservedPaths]]);
        $site = $this->createMock(Site::class);
        $site->method('getSettings')->willReturn($settings);
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        return new SuppressRedirectForReservedPath(new ReservedPathService($siteFinder));
    }

    private function createChangeItem(int $pageId, string $originalSlug): SlugRedirectChangeItem
    {
        return new SlugRedirectChangeItem(
            defaultLanguagePageId: $pageId,
            pageId: $pageId,
            site: $this->createMock(Site::class),
            siteLanguage: $this->createMock(SiteLanguage::class),
            original: ['slug' => $originalSlug],
            sourcesCollection: new RedirectSourceCollection(),
            changed: null,
        );
    }

    /**
     * @param array<string, mixed> $tree
     */
    private static function makeSiteSettings(array $tree): SiteSettings
    {
        // TYPO3 13+ took 3 args (SettingsInterface, tree, flattenedArrayValues);
        // TYPO3 12 took only the tree.
        if (class_exists(\TYPO3\CMS\Core\Settings\Settings::class)) {
            return new SiteSettings(
                new \TYPO3\CMS\Core\Settings\Settings([]),
                $tree,
                [],
            );
        }

        return new SiteSettings($tree);
    }
}
