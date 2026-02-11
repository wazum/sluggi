<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Upgrade;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Upgrade\ClearExcludedDoktypeSlugsWizard;

final class ClearExcludedDoktypeSlugsWizardTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'sluggi' => [
                'exclude_doktypes' => '199,254,255',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_upgrade_wizard.csv');
    }

    #[Test]
    public function updateNecessaryReturnsTrueWhenExcludedPagesHaveSlugs(): void
    {
        $subject = $this->get(ClearExcludedDoktypeSlugsWizard::class);

        self::assertTrue($subject->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsFalseWhenNoExcludedPagesHaveSlugs(): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->update('pages', ['slug' => ''], ['uid' => 2]);
        $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->update('pages', ['slug' => ''], ['uid' => 4]);

        $subject = $this->get(ClearExcludedDoktypeSlugsWizard::class);

        self::assertFalse($subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateClearsSlugsForAllExcludedPageTypes(): void
    {
        $subject = $this->get(ClearExcludedDoktypeSlugsWizard::class);

        $result = $subject->executeUpdate();

        self::assertTrue($result);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/pages_after_upgrade_wizard.csv');
    }

    #[Test]
    public function executeUpdateReturnsTrueWhenNoExcludedPageTypesConfigured(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] = '';

        $subject = $this->get(ClearExcludedDoktypeSlugsWizard::class);

        $result = $subject->executeUpdate();

        self::assertTrue($result);
        // Standard page slug must remain unchanged
        $row = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->select(['slug'], 'pages', ['uid' => 3])
            ->fetchAssociative();
        self::assertSame('/standard-page', $row['slug']);
    }

    #[Test]
    public function executeUpdateDoesNotAffectStandardPages(): void
    {
        $subject = $this->get(ClearExcludedDoktypeSlugsWizard::class);

        $subject->executeUpdate();

        $row = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->select(['slug', 'tx_sluggi_sync'], 'pages', ['uid' => 3])
            ->fetchAssociative();
        self::assertSame('/standard-page', $row['slug']);
        self::assertSame(1, (int)$row['tx_sluggi_sync']);
    }
}
