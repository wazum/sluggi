<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use Wazum\Sluggi\Service\SlugChangeReportStore;

final class SlugChangeReportStoreTest extends TestCase
{
    #[Test]
    public function reportIsNullWhenNothingRecorded(): void
    {
        $store = new SlugChangeReportStore();

        self::assertNull($store->getReport($this->makeBackendUser()));
    }

    #[Test]
    public function incrementPagesUpdatedAccumulatesAcrossCalls(): void
    {
        $beUser = $this->makeBackendUser();
        $store = new SlugChangeReportStore();

        $store->incrementPagesUpdated($beUser);
        $store->incrementPagesUpdated($beUser);
        $store->incrementPagesUpdated($beUser);

        self::assertSame(3, $store->getReport($beUser)['pagesUpdated']);
        self::assertSame(0, $store->getReport($beUser)['redirectsCreated']);
    }

    #[Test]
    public function addEntryKeysByPageIdAndOverwritesCorrelations(): void
    {
        $beUser = $this->makeBackendUser();
        $store = new SlugChangeReportStore();

        $store->addEntry($beUser, 42, ['correlationIdSlugUpdate' => 'a/slug']);
        $store->addEntry($beUser, 42, ['correlationIdSlugUpdate' => 'b/slug']);
        $store->addEntry($beUser, 99, ['correlationIdSlugUpdate' => 'c/slug']);

        $report = $store->getReport($beUser);
        self::assertCount(2, $report['entries']);
        self::assertSame('b/slug', $report['entries'][42]['correlations']['correlationIdSlugUpdate']);
        self::assertSame('c/slug', $report['entries'][99]['correlations']['correlationIdSlugUpdate']);
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

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // CLI mode lets BackendUtility::setUpdateSignal() short-circuit; the
        // module-data slot remains the assertable backing store.
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

    private function makeBackendUser(): BackendUserAuthentication
    {
        $storage = [];
        $mock = $this->createMock(BackendUserAuthentication::class);
        $mock->method('getModuleData')->willReturnCallback(
            static function (string $module) use (&$storage) {
                return $storage[$module] ?? null;
            }
        );
        $mock->method('pushModuleData')->willReturnCallback(
            static function (string $module, mixed $data) use (&$storage): void {
                $storage[$module] = $data;
            }
        );

        return $mock;
    }
}
