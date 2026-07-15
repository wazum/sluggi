<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use Wazum\Sluggi\Service\SlugChangeReportStore;

final class SlugChangeReportStoreTest extends TestCase
{
    #[Test]
    public function reportIsNullWhenNothingRecorded(): void
    {
        $store = new SlugChangeReportStore();

        self::assertNull($store->getReport());
    }

    #[Test]
    public function incrementPagesUpdatedAccumulatesAcrossCalls(): void
    {
        $store = new SlugChangeReportStore();

        $store->incrementPagesUpdated();
        $store->incrementPagesUpdated();
        $store->incrementPagesUpdated();

        self::assertSame(3, $store->getReport()['pagesUpdated']);
        self::assertSame(0, $store->getReport()['redirectsCreated']);
    }

    #[Test]
    public function addEntryKeysByPageIdAndOverwritesCorrelations(): void
    {
        $store = new SlugChangeReportStore();

        $store->addEntry(42, 'Page Forty-Two', ['correlationIdSlugUpdate' => 'a/slug']);
        $store->addEntry(42, 'Page Forty-Two (renamed)', ['correlationIdSlugUpdate' => 'b/slug']);
        $store->addEntry(99, 'Page Ninety-Nine', ['correlationIdSlugUpdate' => 'c/slug']);

        $report = $store->getReport();
        self::assertCount(2, $report['entries']);
        self::assertSame('Page Forty-Two (renamed)', $report['entries'][42]['title']);
        self::assertSame('b/slug', $report['entries'][42]['correlations']['correlationIdSlugUpdate']);
        self::assertSame('c/slug', $report['entries'][99]['correlations']['correlationIdSlugUpdate']);
    }

    #[Test]
    public function discardDoesNotTouchSessionModuleDataOnCli(): void
    {
        // A CLI backend user (CommandLineUserAuthentication) has no user
        // session — getModuleData would fatal on it. Core guards its
        // setUpdateSignal aggregator with Environment::isCli(); discard()
        // must do the same.
        $backendUser = $this->createMock(\TYPO3\CMS\Core\Authentication\BackendUserAuthentication::class);
        $backendUser->expects(self::never())->method('getModuleData');
        $previousBackendUser = $GLOBALS['BE_USER'] ?? null;
        $GLOBALS['BE_USER'] = $backendUser;

        try {
            $store = new SlugChangeReportStore();
            $store->incrementPagesUpdated();
            $store->discard();

            self::assertNull($store->getReport());
        } finally {
            $GLOBALS['BE_USER'] = $previousBackendUser;
        }
    }

    #[Test]
    public function markCandidateRecordsOriginalSlugOnce(): void
    {
        $store = new SlugChangeReportStore();

        $store->markCandidate(42, '/old');
        $store->markCandidate(42, '/SECOND-CALL-IGNORED');

        self::assertSame(['/old'], array_values($store->getCandidates()));
        self::assertSame([42], array_keys($store->getCandidates()));
    }

    #[Test]
    public function markDirectlyEditedExposedViaIsDirectlyEdited(): void
    {
        $store = new SlugChangeReportStore();
        $store->markDirectlyEdited(42, '/old');

        self::assertTrue($store->isDirectlyEdited(42));
        self::assertFalse($store->isDirectlyEdited(43));
    }

    #[Test]
    public function markCountedReturnsTrueOnFirstCallAndFalseAfterwards(): void
    {
        $store = new SlugChangeReportStore();

        self::assertTrue($store->markCounted(42));
        self::assertFalse($store->markCounted(42));
        self::assertTrue($store->markCounted(43));
    }

    #[Test]
    public function discardResetsReportToEmpty(): void
    {
        $store = new SlugChangeReportStore();
        $store->incrementPagesUpdated();
        $store->incrementRedirectsCreated();
        $store->addEntry(42, 'Page Forty-Two', ['correlationIdSlugUpdate' => 'x']);

        $store->discard();

        self::assertNull($store->getReport());
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // CLI mode lets BackendUtility::setUpdateSignal() short-circuit; the
        // in-memory state remains the assertable source of truth.
        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            false,
            __DIR__,
            __DIR__,
            __DIR__,
            __DIR__,
            'php',
            'UNIX',
        );
    }
}
