<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Upgrade;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as CoreExtensionConfiguration;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

#[UpgradeWizard('sluggi_setDefaultExcludedPageTypes')]
final readonly class SetDefaultExcludedPageTypesWizard implements UpgradeWizardInterface
{
    private const DEFAULT_EXCLUDED_DOKTYPES = '199,254';

    public function __construct(
        private CoreExtensionConfiguration $extensionConfiguration,
    ) {
    }

    public function getTitle(): string
    {
        return 'Set default excluded page types for sluggi';
    }

    public function getDescription(): string
    {
        return 'Sets exclude_doktypes to "199,254" (Spacer, Sysfolder) to match TYPO3 core behavior. '
            . 'Without this, copying pages inside sysfolders produces incorrect slugs that include the sysfolder name.';
    }

    public function executeUpdate(): bool
    {
        $config = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi'] ?? [];
        $config['exclude_doktypes'] = self::DEFAULT_EXCLUDED_DOKTYPES;
        $this->extensionConfiguration->set('sluggi', $config);

        return true;
    }

    public function updateNecessary(): bool
    {
        $currentValue = (string)($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['exclude_doktypes'] ?? '');

        return $currentValue === '';
    }

    /**
     * @return list<class-string>
     */
    public function getPrerequisites(): array
    {
        return [];
    }
}
