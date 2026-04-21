<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Redirects\Service\SlugService;
use Wazum\Sluggi\DataHandler\ValidateReservedSlugPath;
use Wazum\Sluggi\Service\ReservedPathService;

final class ValidateReservedSlugPathTest extends TestCase
{
    #[Test]
    public function skipsWhenTableIsNotPages(): void
    {
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->expects(self::never())->method('getSiteByPageId');
        $subject = new ValidateReservedSlugPath(new ReservedPathService($siteFinder));

        $fieldArray = ['slug' => '/api'];
        $subject->processDatamap_postProcessFieldArray(
            'update',
            'tx_news_domain_model_news',
            1,
            $fieldArray,
            $this->createMock(DataHandler::class),
        );

        self::assertSame(['slug' => '/api'], $fieldArray);
    }

    #[Test]
    public function unsetsSlugWhenReservedOnUpdate(): void
    {
        $subject = $this->createSubjectWithReservedPaths(['/api']);

        $fieldArray = ['slug' => '/api', 'title' => 'API'];
        $subject->processDatamap_postProcessFieldArray(
            'update',
            'pages',
            1,
            $fieldArray,
            $this->createMock(DataHandler::class),
        );

        self::assertArrayNotHasKey('slug', $fieldArray);
        self::assertSame('API', $fieldArray['title']);
    }

    #[Test]
    public function leavesSlugAloneWhenNotReserved(): void
    {
        $subject = $this->createSubjectWithReservedPaths(['/api']);

        $fieldArray = ['slug' => '/about'];
        $subject->processDatamap_postProcessFieldArray(
            'update',
            'pages',
            1,
            $fieldArray,
            $this->createMock(DataHandler::class),
        );

        self::assertSame('/about', $fieldArray['slug']);
    }

    #[Test]
    public function leavesSlugAloneWhenAnyUnrelatedSlug(): void
    {
        $subject = $this->createSubjectWithReservedPaths(['/api']);

        $fieldArray = ['slug' => '/blog'];
        $subject->processDatamap_postProcessFieldArray(
            'update',
            'pages',
            1,
            $fieldArray,
            $this->createMock(DataHandler::class),
        );

        self::assertSame('/blog', $fieldArray['slug']);
    }

    #[Test]
    public function rewritesSlugToTemporaryPlaceholderOnCreate(): void
    {
        // On create the slug is rewritten to a non-reserved placeholder with a
        // random suffix on the first path segment. Unlike clearing the field
        // (which TYPO3 turns into the empty string and the form shows as '/',
        // colliding with the site root), this keeps the record at a valid
        // non-reserved slug that the editor has to fix before publishing.
        $subject = $this->createSubjectWithReservedPaths(['/api']);

        $fieldArray = ['slug' => '/api', 'pid' => 1, 'title' => 'API'];
        $subject->processDatamap_postProcessFieldArray(
            'new',
            'pages',
            'NEW123',
            $fieldArray,
            $this->createMock(DataHandler::class),
        );

        self::assertArrayHasKey('slug', $fieldArray);
        self::assertIsString($fieldArray['slug']);
        self::assertMatchesRegularExpression('#^/api-[0-9a-f]{10}$#', (string)$fieldArray['slug']);
    }

    #[Test]
    public function placeholderForNestedReservedSlugEscapesTheReservedPrefix(): void
    {
        $subject = $this->createSubjectWithReservedPaths(['/api']);

        $fieldArray = ['slug' => '/api/foo', 'pid' => 1];
        $subject->processDatamap_postProcessFieldArray(
            'new',
            'pages',
            'NEW123',
            $fieldArray,
            $this->createMock(DataHandler::class),
        );

        $stored = (string)($fieldArray['slug'] ?? '');
        self::assertMatchesRegularExpression('#^/api-[0-9a-f]{10}/foo$#', $stored);
        self::assertStringStartsNotWith('/api/', $stored);
    }

    #[Test]
    public function skipsWhenNestedSlugCascade(): void
    {
        $subject = $this->createSubjectWithReservedPaths(['/api']);
        $dataHandler = $this->createMock(DataHandler::class);
        $correlationId = CorrelationId::forScope('test')
            ->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'slug');
        $dataHandler->method('getCorrelationId')->willReturn($correlationId);

        $fieldArray = ['slug' => '/api'];
        $subject->processDatamap_postProcessFieldArray(
            'update',
            'pages',
            1,
            $fieldArray,
            $dataHandler,
        );

        self::assertSame(['slug' => '/api'], $fieldArray);
    }

    #[Test]
    public function logsErrorOnUpdateReject(): void
    {
        $subject = $this->createSubjectWithReservedPaths(['/api']);
        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->expects(self::once())
            ->method('log')
            ->with('pages', 1, 2, null, 1, self::callback(static fn (mixed $value): bool => is_string($value)));

        $fieldArray = ['slug' => '/api'];
        $subject->processDatamap_postProcessFieldArray(
            'update',
            'pages',
            1,
            $fieldArray,
            $dataHandler,
        );
    }

    #[Test]
    public function logsErrorOnCreate(): void
    {
        $subject = $this->createSubjectWithReservedPaths(['/api']);
        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->expects(self::once())
            ->method('log')
            ->with('pages', 0, 2, null, 1, self::callback(static fn (mixed $value): bool => is_string($value)));

        $fieldArray = ['slug' => '/api', 'pid' => 1];
        $subject->processDatamap_postProcessFieldArray(
            'new',
            'pages',
            'NEW123',
            $fieldArray,
            $dataHandler,
        );
    }

    /**
     * @param list<string> $reservedPaths
     */
    private function createSubjectWithReservedPaths(array $reservedPaths): ValidateReservedSlugPath
    {
        $settings = self::makeSiteSettings(['sluggi' => ['reservedPaths' => $reservedPaths]]);
        $site = $this->createMock(Site::class);
        $site->method('getSettings')->willReturn($settings);
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        return new ValidateReservedSlugPath(new ReservedPathService($siteFinder));
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
