<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Upgrade;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as CoreExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

#[UpgradeWizard('sluggi_enableLockForLockedPages')]
final readonly class EnableLockForLockedPagesWizard implements UpgradeWizardInterface
{
    public function __construct(
        private CoreExtensionConfiguration $extensionConfiguration,
        private ConnectionPool $connectionPool,
    ) {
    }

    public function getTitle(): string
    {
        return 'Enable URL path locking for sluggi';
    }

    public function getDescription(): string
    {
        $lockedPages = $this->countLockedPages();

        return sprintf(
            'Locking a URL path was always available up to sluggi 13, and became the opt-in "lock" '
            . 'setting in 14.0.0 with a default of off. %d %s still carry a lock that is currently '
            . 'ignored, so their URL path follows the page title again and a title change moves it. '
            . 'This turns the setting on so those locks take effect, by writing it to '
            . 'config/system/settings.php. If the setting is pinned in additional.php or another '
            . 'included file, that value wins — set it there instead.',
            $lockedPages,
            $lockedPages === 1 ? 'page does' : 'pages do',
        );
    }

    public function executeUpdate(): bool
    {
        $configuration = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi'] ?? [];
        $configuration['lock'] = '1';
        $this->extensionConfiguration->set('sluggi', $configuration);

        return true;
    }

    public function updateNecessary(): bool
    {
        if ((bool)($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sluggi']['lock'] ?? false)) {
            return false;
        }

        return $this->countLockedPages() > 0;
    }

    private function countLockedPages(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->count('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('slug_locked', $queryBuilder->createNamedParameter(1)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0)),
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @return list<class-string>
     */
    public function getPrerequisites(): array
    {
        return [];
    }
}
