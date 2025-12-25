-- Sluggi E2E Test Fixtures
-- Pages are organized to match test file order

-- =============================================
-- Root page (uid=1)
-- =============================================
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (1, 0, 'Root', '/', 1, 1, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1, 1, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `is_siteroot` = 1, `perms_userid` = 1, `perms_groupid` = 1, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- =============================================
-- page-tree-sync.spec.ts (uses page 2)
-- =============================================
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (2, 1, 'Test Page', '/test-page', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 1;

-- =============================================
-- slug-conflicts.spec.ts (uses pages 3, 4, 5)
-- =============================================
-- Page 3: Conflict test - we'll try to change its slug to /demo (which conflicts with page 4)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (3, 1, 'Conflict Test Page', '/conflict-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- Page 4: Owns /demo slug (used to create conflicts)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`)
VALUES (4, 1, 'Demo', '/demo', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`);

-- Page 5: Has title "Demo" but already de-duplicated slug /demo-1 (for no-conflict-when-correct test)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (5, 1, 'Demo', '/demo-1', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- =============================================
-- slug-editing.spec.ts (uses pages 6, 8)
-- =============================================
-- Page 6: Form save persistence test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (6, 1, 'Save Test Page', '/save-test-page', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- Page 8: Regenerate button test (sync OFF, unique slug)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (8, 1, 'Regenerate Test', '/regenerate-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- =============================================
-- slug-sync.spec.ts (uses pages 7, 9, 10)
-- =============================================
-- Page 7: Sync toggle visual state test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (7, 1, 'Sync Visual Test', '/sync-visual-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- Page 9: Enabling sync triggers regeneration test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (9, 1, 'Sync Regen Test', '/sync-regen-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- Page 10: Source badge immediate visibility test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (10, 1, 'Badge Visibility Test', '/badge-visibility-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- Page 11: Sync toggle visibility regression test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (11, 1, 'Toggle Visible Test', '/toggle-visible-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- Page 12: Source badge hidden when sync off test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (12, 1, 'Badge Hidden Test', '/badge-hidden-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- Page 13: Source badge visible when sync on test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (13, 1, 'Badge Shown Test', '/badge-shown-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- Page 14: Sync state persistence test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (14, 1, 'Sync Persist Test', '/sync-persist-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- Page 15: Sync toggle label test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (15, 1, 'Sync Label Test', '/sync-label-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- =============================================
-- page-move.spec.ts (uses pages 16, 17)
-- =============================================
-- Page 16: Parent page for move test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (16, 1, 'Parent Page', '/parent-page', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- Page 17: Child page to be moved into page 16
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (17, 1, 'Child Page', '/child-page', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- =============================================
-- last-segment-only.spec.ts (uses pages 18, 19)
-- =============================================
-- Page 18: Parent page for last-segment-only tests
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (18, 1, 'Parent Section', '/parent-section', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- Page 19: Child page for last-segment-only tests
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (19, 18, 'Nested Page', '/parent-section/nested-page', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- Page 20: Page with sync enabled for slash-in-title page tree inline edit test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (20, 18, 'Synced Page', '/parent-section/synced-page', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1, 1, 2, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 1;

-- =============================================
-- page-copy.spec.ts (uses pages 21, 22)
-- =============================================
-- Page 21: Source page to be copied
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (21, 1, 'Copy Source', '/copy-source', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- Page 22: Target parent page for copy
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (22, 1, 'Copy Target', '/copy-target', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0;

-- =============================================
-- Backend Users and Groups (for editor tests)
-- =============================================
-- Editor group with proper permissions
INSERT INTO `be_groups` (`uid`, `pid`, `title`, `tables_modify`, `pagetypes_select`, `non_exclude_fields`, `db_mountpoints`, `groupMods`)
VALUES (1, 0, 'Editors', 'pages', '1,254', 'pages:slug,pages:title', '1', 'web_layout,web_list')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `tables_modify` = VALUES(`tables_modify`), `pagetypes_select` = VALUES(`pagetypes_select`), `non_exclude_fields` = VALUES(`non_exclude_fields`), `db_mountpoints` = VALUES(`db_mountpoints`), `groupMods` = VALUES(`groupMods`);

-- Admin user (password: docker)
INSERT INTO `be_users` (`uid`, `pid`, `username`, `password`, `admin`, `usergroup`, `disable`, `deleted`)
VALUES (1, 0, 'admin', '$argon2id$v=19$m=65536,t=4,p=1$cDN1QXFkY21Rd1NGR2YwMQ$6co8ugqO4m6sdtCw08XSe9ayMuKNzgUDKpcfU9sSODg', 1, '', 0, 0)
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`), `password` = VALUES(`password`), `admin` = 1;

-- Editor user (non-admin, password: docker) - only in group 2 for hierarchy permission tests
INSERT INTO `be_users` (`uid`, `pid`, `username`, `password`, `admin`, `usergroup`, `disable`, `deleted`)
VALUES (2, 0, 'editor', '$argon2id$v=19$m=65536,t=4,p=1$cDN1QXFkY21Rd1NGR2YwMQ$6co8ugqO4m6sdtCw08XSe9ayMuKNzgUDKpcfU9sSODg', 0, '2', 0, 0)
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`), `password` = VALUES(`password`), `admin` = 0, `usergroup` = '2';

-- =============================================
-- hierarchy-permission.spec.ts (uses pages 23-26)
-- =============================================
-- Editor group for Institute section (uid=2) - includes page 18 for last-segment-only tests and page 25 for hierarchy tests
-- Has access to sync/lock fields (tx_sluggi_sync, slug_locked) for full toggle visibility
INSERT INTO `be_groups` (`uid`, `pid`, `title`, `tables_modify`, `pagetypes_select`, `non_exclude_fields`, `db_mountpoints`, `groupMods`)
VALUES (2, 0, 'Institute Editors', 'pages', '1,254', 'pages:slug,pages:title,pages:nav_title,pages:tx_sluggi_sync,pages:slug_locked', '18,25', 'web_layout,web_list')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `tables_modify` = VALUES(`tables_modify`), `pagetypes_select` = VALUES(`pagetypes_select`), `non_exclude_fields` = VALUES(`non_exclude_fields`), `db_mountpoints` = VALUES(`db_mountpoints`), `groupMods` = VALUES(`groupMods`);

-- Page 23: Hierarchy test - root section (admin only, editor cannot edit)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (23, 1, 'Organization', '/organization', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 0, 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 0, `perms_everybody` = 1;

-- Page 24: Hierarchy test - department section (admin only, editor cannot edit)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (24, 23, 'Department', '/organization/department', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 0, 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 0, `perms_everybody` = 1;

-- Page 25: Hierarchy test - institute section (editor can edit via group 2)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (25, 24, 'Institute', '/organization/department/institute', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 31, 31, 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 2, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 1;

-- Page 26: Hierarchy test - about us (editor can edit via group 2)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (26, 25, 'About Us', '/organization/department/institute/about-us', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 31, 31, 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 2, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 1;

-- =============================================
-- excluded-doktypes.spec.ts (uses page 27)
-- =============================================
-- Page 27: SysFolder with existing slug (legacy data) - slug should be cleared on save, field should be hidden
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`)
VALUES (27, 1, 'News Records', '/news-records', 254, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `doktype` = 254, `tx_sluggi_sync` = 0;

-- =============================================
-- slug-lock.spec.ts (uses pages 28-32)
-- =============================================
-- Page 28: Lock toggle visibility test (unlocked)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`)
VALUES (28, 1, 'Lock Toggle Test', '/lock-toggle-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `slug_locked` = 0;

-- Page 29: Lock visual state test (unlocked initially)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`)
VALUES (29, 1, 'Lock Visual Test', '/lock-visual-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `slug_locked` = 0;

-- Page 30: Lock persistence test (unlocked initially)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`)
VALUES (30, 1, 'Lock Persist Test', '/lock-persist-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `slug_locked` = 0;

-- Page 31: Locked page editing test (locked)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`)
VALUES (31, 1, 'Already Locked Page', '/already-locked-page', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `slug_locked` = 1;

-- Page 32: Lock vs Sync mutual exclusion test (unlocked and unsynced initially)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`)
VALUES (32, 1, 'Lock Sync Test', '/lock-sync-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `slug_locked` = 0;

-- Page 33: Sync vs Lock mutual exclusion test (synced initially)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`)
VALUES (33, 1, 'Sync Lock Test', '/sync-lock-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 1, `slug_locked` = 0;

-- =============================================
-- field-access-restriction.spec.ts (uses pages 34-35)
-- Tests for users who can see pages but cannot toggle sync/lock fields
-- =============================================
-- Editor group 3: NO access to sync/lock fields (only basic slug access)
INSERT INTO `be_groups` (`uid`, `pid`, `title`, `tables_modify`, `pagetypes_select`, `non_exclude_fields`, `db_mountpoints`, `groupMods`)
VALUES (3, 0, 'Restricted Editors', 'pages', '1,254', 'pages:slug,pages:title', '34', 'web_layout,web_list')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `tables_modify` = VALUES(`tables_modify`), `pagetypes_select` = VALUES(`pagetypes_select`), `non_exclude_fields` = VALUES(`non_exclude_fields`), `db_mountpoints` = VALUES(`db_mountpoints`), `groupMods` = VALUES(`groupMods`);

-- Editor user 3: non-admin in group 3 (no sync/lock field access)
INSERT INTO `be_users` (`uid`, `pid`, `username`, `password`, `admin`, `usergroup`, `disable`, `deleted`)
VALUES (3, 0, 'restricted_editor', '$argon2id$v=19$m=65536,t=4,p=1$cDN1QXFkY21Rd1NGR2YwMQ$6co8ugqO4m6sdtCw08XSe9ayMuKNzgUDKpcfU9sSODg', 0, '3', 0, 0)
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`), `password` = VALUES(`password`), `admin` = 0, `usergroup` = '3';

-- Page 34: Parent for restricted editor tests
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (34, 1, 'Restricted Section', '/restricted-section', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 0, 1, 3, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `slug_locked` = 0, `perms_groupid` = 3;

-- Page 35: Synced page that restricted editor can see but not toggle sync
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (35, 34, 'Synced No Toggle', '/restricted-section/synced-no-toggle', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1, 0, 1, 3, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 1, `slug_locked` = 0, `perms_groupid` = 3;

-- Page 36: Locked page that restricted editor can see but not toggle lock
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (36, 34, 'Locked No Toggle', '/restricted-section/locked-no-toggle', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 1, 3, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `slug_locked` = 1, `perms_groupid` = 3;
