<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Upgrade;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as CoreExtensionConfiguration;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use Wazum\Sluggi\Service\MasiCompatibilityService;

#[UpgradeWizard('sluggi_setDefaultExcludedPageTypes')]
final readonly class SetDefaultExcludedPageTypesWizard implements UpgradeWizardInterface
{
    private const DEFAULT_EXCLUDED_DOKTYPES = '199,254';

    /**
     * masi lets every folder decide for itself whether it appears in the paths of
     * its subpages, so the global exclusion must not take that decision away.
     */
    private const DEFAULT_EXCLUDED_DOKTYPES_WITH_MASI = '199';

    public function __construct(
        private CoreExtensionConfiguration $extensionConfiguration,
        private MasiCompatibilityService $masiCompatibilityService,
    ) {
    }

    public function getTitle(): string
    {
        return 'Set default excluded page types for sluggi';
    }

    public function getDescription(): string
    {
        if ($this->masiCompatibilityService->isActive()) {
            return 'Sets exclude_doktypes to "199" (Spacer) to match TYPO3 core behavior. Folders (254) '
                . 'are left out because "masi" is installed: there each folder decides for itself whether '
                . 'it appears in the paths of its subpages, and a global exclusion would take that '
                . 'decision away. Add 254 by hand to keep folders out of URL paths regardless.';
        }

        return 'Sets exclude_doktypes to "199,254" (Spacer, Sysfolder) to match TYPO3 core behavior. '
            . 'Without this, copying pages inside sysfolders produces incorrect slugs that include the sysfolder name.';
    }

    public function executeUpdate(): bool
    {
        $config = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi'] ?? [];
        $config['exclude_doktypes'] = $this->defaultExcludedPageTypes();
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

    private function defaultExcludedPageTypes(): string
    {
        return $this->masiCompatibilityService->isActive()
            ? self::DEFAULT_EXCLUDED_DOKTYPES_WITH_MASI
            : self::DEFAULT_EXCLUDED_DOKTYPES;
    }
}
