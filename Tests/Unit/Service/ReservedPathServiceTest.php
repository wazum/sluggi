<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\SiteFinder;
use Wazum\Sluggi\Service\ReservedPathService;

final class ReservedPathServiceTest extends TestCase
{
    #[Test]
    public function isReservedReturnsTrueOnExactMatch(): void
    {
        $subject = new ReservedPathService($this->createMock(SiteFinder::class));
        self::assertTrue($subject->isReserved('/api', ['/api']));
    }

    #[Test]
    public function isReservedReturnsFalseWhenPatternsAreEmpty(): void
    {
        $subject = new ReservedPathService($this->createMock(SiteFinder::class));
        self::assertFalse($subject->isReserved('/api', []));
    }

    #[Test]
    public function isReservedReturnsTrueForPathOneLevelBelowPattern(): void
    {
        $subject = new ReservedPathService($this->createMock(SiteFinder::class));
        self::assertTrue($subject->isReserved('/api/v1', ['/api']));
    }

    #[Test]
    public function isReservedReturnsTrueForPathMultipleLevelsBelowPattern(): void
    {
        $subject = new ReservedPathService($this->createMock(SiteFinder::class));
        self::assertTrue($subject->isReserved('/api/v1/users', ['/api']));
    }

    #[Test]
    public function isReservedReturnsFalseWhenPrefixMatchesButNotAtSegmentBoundary(): void
    {
        $subject = new ReservedPathService($this->createMock(SiteFinder::class));
        self::assertFalse($subject->isReserved('/api-docs', ['/api']));
    }

    #[Test]
    public function isReservedReturnsFalseForDifferentSuffix(): void
    {
        $subject = new ReservedPathService($this->createMock(SiteFinder::class));
        self::assertFalse($subject->isReserved('/apis', ['/api']));
    }

    #[Test]
    public function isReservedReturnsFalseWhenPatternIsNotAtRoot(): void
    {
        $subject = new ReservedPathService($this->createMock(SiteFinder::class));
        self::assertFalse($subject->isReserved('/other/api', ['/api']));
    }

    #[Test]
    public function isReservedReturnsFalseForEmptySlug(): void
    {
        $subject = new ReservedPathService($this->createMock(SiteFinder::class));
        self::assertFalse($subject->isReserved('', ['/api']));
    }

    #[Test]
    public function normalisePatternsReturnsValidListUnchanged(): void
    {
        self::assertSame(['/api', '/typo3'], ReservedPathService::normalisePatterns(['/api', '/typo3']));
    }

    #[Test]
    public function normalisePatternsStripsTrailingSlash(): void
    {
        self::assertSame(['/api'], ReservedPathService::normalisePatterns(['/api/']));
    }

    #[Test]
    public function normalisePatternsDropsEntriesWithoutLeadingSlash(): void
    {
        self::assertSame([], ReservedPathService::normalisePatterns(['api']));
    }

    #[Test]
    public function normalisePatternsDropsEmptyStrings(): void
    {
        self::assertSame([], ReservedPathService::normalisePatterns(['']));
    }

    #[Test]
    public function normalisePatternsDropsNonStringEntries(): void
    {
        self::assertSame([], ReservedPathService::normalisePatterns([123, null, false]));
    }

    #[Test]
    public function normalisePatternsKeepsOnlyValidEntriesInMixedInput(): void
    {
        self::assertSame(
            ['/api', '/foo'],
            ReservedPathService::normalisePatterns(['/api', 'bad', '/foo/']),
        );
    }

    #[Test]
    public function getReservedPathsForSiteReturnsNormalisedListFromSettings(): void
    {
        $settings = self::makeSiteSettings(['sluggi' => ['reservedPaths' => ['/api/']]]);
        $site = $this->createMock(Site::class);
        $site->method('getSettings')->willReturn($settings);

        $subject = new ReservedPathService($this->createMock(SiteFinder::class));

        self::assertSame(['/api'], $subject->getReservedPathsForSite($site));
    }

    #[Test]
    public function getReservedPathsForSiteReturnsEmptyListWhenSettingIsNotArray(): void
    {
        $settings = self::makeSiteSettings([]);
        $site = $this->createMock(Site::class);
        $site->method('getSettings')->willReturn($settings);

        $subject = new ReservedPathService($this->createMock(SiteFinder::class));

        self::assertSame([], $subject->getReservedPathsForSite($site));
    }

    #[Test]
    public function findSiteForPageReturnsSiteFromFinder(): void
    {
        $site = $this->createMock(Site::class);
        $finder = $this->createMock(SiteFinder::class);
        $finder->method('getSiteByPageId')->with(42)->willReturn($site);

        $subject = new ReservedPathService($finder);

        self::assertSame($site, $subject->findSiteForPage(42));
    }

    #[Test]
    public function findSiteForPageReturnsNullWhenSiteNotFound(): void
    {
        $finder = $this->createMock(SiteFinder::class);
        $finder->method('getSiteByPageId')->willThrowException(new SiteNotFoundException('nope', 1));

        $subject = new ReservedPathService($finder);

        self::assertNull($subject->findSiteForPage(42));
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
