<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Upgrade;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Upgrade\SetDefaultExcludedPageTypesWizard;

final class SetDefaultExcludedPageTypesWizardTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    #[Test]
    public function updateNecessaryReturnsTrueWhenExcludeDoktypesIsEmpty(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] = '';

        $subject = $this->get(SetDefaultExcludedPageTypesWizard::class);

        self::assertTrue($subject->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsFalseWhenExcludeDoktypesIsAlreadySet(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] = '199,254';

        $subject = $this->get(SetDefaultExcludedPageTypesWizard::class);

        self::assertFalse($subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateSetsDefaultExcludedPageTypes(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] = '';

        $subject = $this->get(SetDefaultExcludedPageTypesWizard::class);
        $result = $subject->executeUpdate();

        self::assertTrue($result);
        self::assertSame(
            '199,254',
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes']
        );
    }
}
