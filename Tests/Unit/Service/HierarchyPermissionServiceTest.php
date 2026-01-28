<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Sluggi\Service\HierarchyPermissionService;

final class HierarchyPermissionServiceTest extends TestCase
{
    #[Test]
    public function getLockedPrefixReturnsEmptyStringWhenUserCanEditAllPages(): void
    {
        $subject = new HierarchyPermissionService();

        // TYPO3's BEgetRootLine returns from current page to root
        $rootLine = [
            ['uid' => 3, 'slug' => '/home/department'],
            ['uid' => 2, 'slug' => '/home'],
            ['uid' => 1, 'slug' => '/'],
            ['uid' => 0, 'slug' => ''],
        ];
        $editablePageUids = [1, 2, 3];

        $result = $subject->getLockedPrefix($rootLine, $editablePageUids, '/home/department/institute');

        self::assertSame('', $result);
    }

    #[Test]
    public function getLockedPrefixSkipsRootPageWhenCalculatingPrefix(): void
    {
        $subject = new HierarchyPermissionService();

        // Real-world scenario: root page has perms_everybody=31 (all permissions)
        // so it appears in editablePageUids even though user only has content access to deeper pages
        $rootLine = [
            ['uid' => 28, 'slug' => '/organization/department/institute/about-us'],
            ['uid' => 27, 'slug' => '/organization/department/institute'],
            ['uid' => 26, 'slug' => '/organization/department'],
            ['uid' => 25, 'slug' => '/organization'],
            ['uid' => 1, 'slug' => '/'],
            ['uid' => 0, 'slug' => ''],
        ];
        // Root page (uid=1) is editable due to permissive settings, but should be skipped
        $editablePageUids = [28, 27, 1];

        $result = $subject->getLockedPrefix($rootLine, $editablePageUids, '/organization/department/institute/about-us');

        // Should return parent of page 27 (first meaningful editable page), not empty string from root
        self::assertSame('/organization/department', $result);
    }

    #[Test]
    public function getLockedPrefixReturnsParentOfTopmostEditablePage(): void
    {
        $subject = new HierarchyPermissionService();

        // TYPO3's BEgetRootLine returns from current page to root
        $rootLine = [
            ['uid' => 3, 'slug' => '/home/department'],
            ['uid' => 2, 'slug' => '/home'],
            ['uid' => 1, 'slug' => '/'],
            ['uid' => 0, 'slug' => ''],
        ];
        $editablePageUids = [3];

        $result = $subject->getLockedPrefix($rootLine, $editablePageUids, '/home/department/institute');

        self::assertSame('/home', $result);
    }

    #[Test]
    public function getLockedPrefixReturnsPartialPrefixBasedOnPermissions(): void
    {
        $subject = new HierarchyPermissionService();

        // TYPO3's BEgetRootLine returns from current page to root
        $rootLine = [
            ['uid' => 4, 'slug' => '/home/department/institute'],
            ['uid' => 3, 'slug' => '/home/department'],
            ['uid' => 2, 'slug' => '/home'],
            ['uid' => 1, 'slug' => '/'],
            ['uid' => 0, 'slug' => ''],
        ];
        $editablePageUids = [3, 4];

        $result = $subject->getLockedPrefix($rootLine, $editablePageUids, '/home/department/institute/about-us');

        self::assertSame('/home', $result);
    }

    #[Test]
    public function getEditablePageUidsReturnsOnlyPagesWithEditPermission(): void
    {
        $subject = new HierarchyPermissionService();

        // TYPO3's BEgetRootLine returns from current page to root
        $rootLine = [
            ['uid' => 3, 'perms_user' => 31, 'perms_userid' => 1, 'perms_groupid' => 0, 'perms_group' => 0, 'perms_everybody' => 0],
            ['uid' => 2, 'perms_user' => 31, 'perms_userid' => 2, 'perms_groupid' => 0, 'perms_group' => 0, 'perms_everybody' => 0],
            ['uid' => 1, 'perms_user' => 31, 'perms_userid' => 1, 'perms_groupid' => 0, 'perms_group' => 0, 'perms_everybody' => 0],
            ['uid' => 0, 'perms_user' => 0],
        ];

        $mockCallback = static fn (array $page): bool => ($page['perms_userid'] ?? 0) === 1;

        $result = $subject->getEditablePageUids($rootLine, $mockCallback);

        self::assertSame([3, 1], $result);
    }

    #[Test]
    public function validateSlugChangeReturnsTrueWhenLockedPrefixUnchanged(): void
    {
        $subject = new HierarchyPermissionService();

        $lockedPrefix = '/home/department';
        $newSlug = '/home/department/new-institute';

        $result = $subject->validateSlugChange($lockedPrefix, $newSlug);

        self::assertTrue($result);
    }

    #[Test]
    public function validateSlugChangeReturnsFalseWhenLockedPrefixChanged(): void
    {
        $subject = new HierarchyPermissionService();

        $lockedPrefix = '/home/department';
        $newSlug = '/home/other-department/institute';

        $result = $subject->validateSlugChange($lockedPrefix, $newSlug);

        self::assertFalse($result);
    }

    #[Test]
    public function validateSlugChangeReturnsTrueWhenNoLockedPrefix(): void
    {
        $subject = new HierarchyPermissionService();

        $lockedPrefix = '';
        $newSlug = '/completely/different/path';

        $result = $subject->validateSlugChange($lockedPrefix, $newSlug);

        self::assertTrue($result);
    }

    /**
     * @return array<string, array{newSlug: string, lockedPrefix: string, expected: bool}>
     */
    public static function slugValidationDataProvider(): array
    {
        return [
            'changing only last segment is valid' => [
                'newSlug' => '/home/department/new-name',
                'lockedPrefix' => '/home/department',
                'expected' => true,
            ],
            'changing segment within editable range is valid' => [
                'newSlug' => '/home/department/new-institute/new-about',
                'lockedPrefix' => '/home',
                'expected' => true,
            ],
            'changing locked segment is invalid' => [
                'newSlug' => '/home/other-dept/institute',
                'lockedPrefix' => '/home/department',
                'expected' => false,
            ],
            'removing locked segment is invalid' => [
                'newSlug' => '/home/institute',
                'lockedPrefix' => '/home/department',
                'expected' => false,
            ],
            'adding segment within editable range is valid' => [
                'newSlug' => '/home/department/institute/sub-page',
                'lockedPrefix' => '/home',
                'expected' => true,
            ],
        ];
    }

    #[Test]
    #[DataProvider('slugValidationDataProvider')]
    public function validateSlugChangeReturnsExpectedResult(
        string $newSlug,
        string $lockedPrefix,
        bool $expected,
    ): void {
        $subject = new HierarchyPermissionService();

        $result = $subject->validateSlugChange($lockedPrefix, $newSlug);

        self::assertSame($expected, $result);
    }
}
