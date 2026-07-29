<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Upgrade;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Upgrade\EnableLockForLockedPagesWizard;

final class EnableLockForLockedPagesWizardTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    #[Test]
    public function updateNecessaryWhenLockedPagesExistAndTheFeatureIsOff(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_with_locked_slugs.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['lock'] = '0';

        $subject = $this->get(EnableLockForLockedPagesWizard::class);

        self::assertTrue($subject->updateNecessary());
    }

    #[Test]
    public function descriptionNamesHowManyPagesCarryALock(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_with_locked_slugs.csv');

        $subject = $this->get(EnableLockForLockedPagesWizard::class);

        self::assertStringContainsString('1 page', $subject->getDescription());
    }

    #[Test]
    public function executeUpdateTurnsLockingOnAndKeepsOtherSettings(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_with_locked_slugs.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi'] = [
            'lock' => '0',
            'exclude_doktypes' => '199,254',
        ];

        $subject = $this->get(EnableLockForLockedPagesWizard::class);

        self::assertTrue($subject->executeUpdate());
        self::assertSame('1', $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['lock']);
        self::assertSame('199,254', $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes']);
    }

    #[Test]
    public function noUpdateNecessaryWhenOnlyDeletedPagesCarryALock(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_with_deleted_locked_slug.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['lock'] = '0';

        $subject = $this->get(EnableLockForLockedPagesWizard::class);

        self::assertFalse($subject->updateNecessary());
    }

    #[Test]
    public function noUpdateNecessaryWhenTheFeatureIsAlreadyOn(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_with_locked_slugs.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['lock'] = '1';

        $subject = $this->get(EnableLockForLockedPagesWizard::class);

        self::assertFalse($subject->updateNecessary());
    }
}
