<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Upgrade;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Upgrade\CoupleMasiExclusionWizard;

final class CoupleMasiExclusionWizardTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
        'b13/masi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_diverging_masi_exclusion.csv');
    }

    #[Test]
    public function updateNecessaryWhenATranslationDivergesFromItsDefault(): void
    {
        self::assertTrue($this->get(CoupleMasiExclusionWizard::class)->updateNecessary());
    }

    #[Test]
    public function executeUpdateAlignsTranslationsWithTheirDefault(): void
    {
        $subject = $this->get(CoupleMasiExclusionWizard::class);

        self::assertTrue($subject->executeUpdate());

        $flags = $this->getConnectionPool()->getConnectionForTable('pages')
            ->executeQuery('SELECT uid, exclude_slug_for_subpages FROM pages ORDER BY uid')
            ->fetchAllKeyValue();

        self::assertSame('1', (string)$flags[10], 'Diverging translation is aligned');
        self::assertSame('0', (string)$flags[11], 'Already matching translation is untouched');
        self::assertSame('1', (string)$flags[2], 'Default language rows are never changed');
        self::assertFalse($subject->updateNecessary());
    }
}
