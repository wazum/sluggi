<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Upgrade;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Upgrade\ClearExcludedPageTypeSlugsWizard;
use Wazum\Sluggi\Upgrade\SetDefaultExcludedPageTypesWizard;

final class WizardsWithMasiInstalledTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
        'b13/masi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    #[Test]
    public function theDefaultDoesNotTakeFoldersOutOfUrlPaths(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] = '';

        $subject = $this->get(SetDefaultExcludedPageTypesWizard::class);
        $subject->executeUpdate();

        self::assertSame(
            '199',
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'],
            'With "masi" installed each folder decides for itself, so 254 must stay out of the global list',
        );
    }

    #[Test]
    public function clearingSlugsKeepsTheFolderPathsMasiNeeds(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages_for_upgrade_wizard.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] = '199,254';

        $subject = $this->get(ClearExcludedPageTypeSlugsWizard::class);
        $subject->executeUpdate();

        $slugs = $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->select(['uid', 'slug'], 'pages', [])
            ->fetchAllKeyValue();

        self::assertSame('/sysfolder', $slugs[2], 'A folder may still contribute its segment via "masi"');
        self::assertSame('', $slugs[4], 'Spacers never appear in a path, "masi" or not');
    }
}
