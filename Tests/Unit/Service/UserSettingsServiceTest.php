<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use Wazum\Sluggi\Service\UserSettingsService;

final class UserSettingsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        parent::tearDown();
    }

    private function setUpBackendUser(array $uc): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->uc = $uc;
        $GLOBALS['BE_USER'] = $backendUser;
    }

    #[Test]
    public function isCollapsedControlsEnabledReturnsFalseWhenNoBackendUser(): void
    {
        unset($GLOBALS['BE_USER']);

        $subject = new UserSettingsService();

        self::assertFalse($subject->isCollapsedControlsEnabled());
    }

    #[Test]
    public function isCollapsedControlsEnabledReturnsFalseByDefault(): void
    {
        $this->setUpBackendUser([]);

        $subject = new UserSettingsService();

        self::assertFalse($subject->isCollapsedControlsEnabled());
    }

    #[Test]
    public function isCollapsedControlsEnabledReturnsTrueWhenUserSettingIsTrue(): void
    {
        $this->setUpBackendUser([
            'sluggiCollapsedControls' => true,
        ]);

        $subject = new UserSettingsService();

        self::assertTrue($subject->isCollapsedControlsEnabled());
    }

    #[Test]
    public function isCollapsedControlsEnabledReturnsFalseWhenUserSettingIsFalse(): void
    {
        $this->setUpBackendUser([
            'sluggiCollapsedControls' => false,
        ]);

        $subject = new UserSettingsService();

        self::assertFalse($subject->isCollapsedControlsEnabled());
    }

    #[Test]
    public function isCollapsedControlsEnabledReturnsTrueWhenUserSettingIs1AsString(): void
    {
        $this->setUpBackendUser([
            'sluggiCollapsedControls' => '1',
        ]);

        $subject = new UserSettingsService();

        self::assertTrue($subject->isCollapsedControlsEnabled());
    }

    #[Test]
    public function isCollapsedControlsEnabledReturnsFalseWhenUserSettingIs0AsString(): void
    {
        $this->setUpBackendUser([
            'sluggiCollapsedControls' => '0',
        ]);

        $subject = new UserSettingsService();

        self::assertFalse($subject->isCollapsedControlsEnabled());
    }
}
