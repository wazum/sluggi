-- Sluggi E2E Test Fixtures

-- =============================================
-- Backend Groups
-- =============================================
DELETE FROM `be_groups` WHERE `uid` IN (2, 3);

-- Group 2: Institute Editors (db_mountpoints: 18,27, has sync/lock field access)
INSERT INTO `be_groups` (`uid`, `pid`, `tstamp`, `crdate`, `hidden`, `deleted`, `title`, `tables_modify`, `pagetypes_select`, `non_exclude_fields`, `db_mountpoints`, `groupMods`)
VALUES (2, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 0, 'Institute Editors', 'pages', '1,254', 'pages:slug,pages:title,pages:nav_title,pages:tx_sluggi_sync,pages:slug_locked,pages:tx_sluggi_full_path', '18,27', 'web_layout,web_list');

-- Group 3: Restricted Editors (db_mountpoints: 36, no sync/lock field access)
INSERT INTO `be_groups` (`uid`, `pid`, `tstamp`, `crdate`, `hidden`, `deleted`, `title`, `tables_modify`, `pagetypes_select`, `non_exclude_fields`, `db_mountpoints`, `groupMods`)
VALUES (3, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 0, 'Restricted Editors', 'pages', '1,254', 'pages:slug,pages:title', '36', 'web_layout,web_list');

-- =============================================
-- Backend Users
-- =============================================
DELETE FROM `be_users` WHERE `uid` IN (2, 3, 4);

-- Admin (password: docker)
INSERT INTO `be_users` (`uid`, `pid`, `tstamp`, `crdate`, `username`, `password`, `admin`, `usergroup`, `disable`, `deleted`)
VALUES (1, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 'admin', '$argon2id$v=19$m=65536,t=4,p=1$cDN1QXFkY21Rd1NGR2YwMQ$6co8ugqO4m6sdtCw08XSe9ayMuKNzgUDKpcfU9sSODg', 1, '', 0, 0)
ON DUPLICATE KEY UPDATE `tstamp` = UNIX_TIMESTAMP(), `username` = VALUES(`username`), `password` = VALUES(`password`), `admin` = 1, `disable` = 0, `deleted` = 0;

-- Collapsed Controls Admin (password: docker, uc includes sluggiCollapsedControls for collapsed-controls E2E test)
INSERT INTO `be_users` (`uid`, `pid`, `tstamp`, `crdate`, `username`, `password`, `admin`, `usergroup`, `disable`, `deleted`, `uc`)
VALUES (4, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 'collapsed_admin', '$argon2id$v=19$m=65536,t=4,p=1$cDN1QXFkY21Rd1NGR2YwMQ$6co8ugqO4m6sdtCw08XSe9ayMuKNzgUDKpcfU9sSODg', 1, '', 0, 0, 'a:1:{s:23:"sluggiCollapsedControls";s:1:"1";}');

-- Editor (group 2, options=3 enables "Mount from groups: DB Mounts + File Mounts")
INSERT INTO `be_users` (`uid`, `pid`, `tstamp`, `crdate`, `username`, `password`, `admin`, `usergroup`, `options`, `disable`, `deleted`)
VALUES (2, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 'editor', '$argon2id$v=19$m=65536,t=4,p=1$cDN1QXFkY21Rd1NGR2YwMQ$6co8ugqO4m6sdtCw08XSe9ayMuKNzgUDKpcfU9sSODg', 0, '2', 3, 0, 0);

-- Restricted editor (group 3, options=3 enables "Mount from groups: DB Mounts + File Mounts")
INSERT INTO `be_users` (`uid`, `pid`, `tstamp`, `crdate`, `username`, `password`, `admin`, `usergroup`, `options`, `disable`, `deleted`)
VALUES (3, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 'restricted_editor', '$argon2id$v=19$m=65536,t=4,p=1$cDN1QXFkY21Rd1NGR2YwMQ$6co8ugqO4m6sdtCw08XSe9ayMuKNzgUDKpcfU9sSODg', 0, '3', 3, 0, 0);

-- =============================================
-- Root page (uid=1)
-- =============================================
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (1, 0, 'Root', '/', 1, 1, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `is_siteroot` = 1, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- =============================================
-- page-tree-sync.spec.ts (uses page 2)
-- =============================================
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (2, 1, 'Test Page', '/test-page', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 1, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- =============================================
-- slug-conflicts.spec.ts (uses pages 3, 4, 5)
-- =============================================
-- Page 3: Conflict test - we'll try to change its slug to /demo (which conflicts with page 4)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (3, 1, 'Conflict Test Page', '/conflict-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 4: Owns /demo slug (used to create conflicts)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (4, 1, 'Demo', '/demo', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 5: Has title "Demo" but already de-duplicated slug /demo-1 (for no-conflict-when-correct test)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (5, 1, 'Demo', '/demo-1', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- =============================================
-- slug-editing.spec.ts (uses pages 6, 8)
-- =============================================
-- Page 6: Form save persistence test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (6, 1, 'Save Test Page', '/save-test-page', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 8: Regenerate button test (sync OFF, unique slug)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (8, 1, 'Regenerate Test', '/regenerate-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- =============================================
-- slug-sync.spec.ts (uses pages 7, 9, 10)
-- =============================================
-- Page 7: Sync toggle visual state test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (7, 1, 'Sync Visual Test', '/sync-visual-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 9: Enabling sync triggers regeneration test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (9, 1, 'Sync Regen Test', '/sync-regen-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 10: Source badge immediate visibility test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (10, 1, 'Badge Visibility Test', '/badge-visibility-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 11: Sync toggle visibility regression test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (11, 1, 'Toggle Visible Test', '/toggle-visible-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 12: Source badge hidden when sync off test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (12, 1, 'Badge Hidden Test', '/badge-hidden-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 13: Source badge visible when sync on test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (13, 1, 'Badge Shown Test', '/badge-shown-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 14: Sync state persistence test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (14, 1, 'Sync Persist Test', '/sync-persist-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 15: Sync toggle label test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (15, 1, 'Sync Label Test', '/sync-label-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- =============================================
-- page-move.spec.ts (uses pages 16, 17)
-- =============================================
-- Page 16: Parent page for move test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (16, 1, 'Parent Page', '/parent-page', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 17: Child page to be moved into page 16
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (17, 1, 'Child Page', '/child-page', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- =============================================
-- last-segment-only.spec.ts (uses pages 18-22)
-- =============================================
-- Page 18: Parent page for last-segment-only tests
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (18, 1, 'Parent Section', '/parent-section', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 2, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 0;

-- Page 19: Child page for read-only last-segment-only tests
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (19, 18, 'Nested Page', '/parent-section/nested-page', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 2, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 0;

-- Page 20: For "editor can change only the last segment" test (saves)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (20, 18, 'Change Segment Test', '/parent-section/change-segment-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 2, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 0;

-- Page 21: For "backend blocks attempt to change parent segment" test (saves)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (21, 18, 'Block Parent Change Test', '/parent-section/block-parent-change-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 2, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 0;

-- Page 22: Synced page for "slash in title via page tree inline edit" test (saves)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (22, 18, 'Synced Page', '/parent-section/synced-page', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1, 1, 2, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 1, `perms_userid` = 1, `perms_groupid` = 2, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 0;

-- =============================================
-- page-copy.spec.ts (uses pages 23, 24)
-- =============================================
-- Page 23: Source page to be copied
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (23, 1, 'Copy Source', '/copy-source', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 24: Target parent page for copy
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (24, 1, 'Copy Target', '/copy-target', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- =============================================
-- hierarchy-permission.spec.ts (pages 25-28)
-- =============================================

-- Page 25: Hierarchy test - root section (admin only, editor cannot edit)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (25, 1, 'Organization', '/organization', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 0, 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 0, `perms_everybody` = 1;

-- Page 26: Hierarchy test - department section (admin only, editor cannot edit)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (26, 25, 'Department', '/organization/department', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 0, 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 0, `perms_everybody` = 1;

-- Page 27: Hierarchy test - institute section (editor can edit via group 2)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (27, 26, 'Institute', '/organization/department/institute', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 31, 31, 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 2, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 1;

-- Page 28: Hierarchy test - about us (editor can edit via group 2)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (28, 27, 'About Us', '/organization/department/institute/about-us', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 31, 31, 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 2, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 1;

-- =============================================
-- excluded-doktypes.spec.ts (uses page 29)
-- =============================================
-- Page 29: SysFolder with existing slug (legacy data) - slug should be cleared on save, field should be hidden
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (29, 1, 'News Records', '/news-records', 254, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `doktype` = 254, `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- =============================================
-- slug-lock.spec.ts (uses pages 30-35)
-- =============================================
-- Page 30: Lock toggle visibility test (unlocked)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (30, 1, 'Lock Toggle Test', '/lock-toggle-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `slug_locked` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 31: Lock visual state test (unlocked initially)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (31, 1, 'Lock Visual Test', '/lock-visual-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `slug_locked` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 32: Lock persistence test (unlocked initially)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (32, 1, 'Lock Persist Test', '/lock-persist-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `slug_locked` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 33: Locked page editing test (locked)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (33, 1, 'Already Locked Page', '/already-locked-page', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `slug_locked` = 1, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 34: Lock vs Sync mutual exclusion test (unlocked and unsynced initially)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (34, 1, 'Lock Sync Test', '/lock-sync-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `slug_locked` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 35: Sync vs Lock mutual exclusion test (synced initially)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (35, 1, 'Sync Lock Test', '/sync-lock-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1, 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 1, `slug_locked` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- =============================================
-- field-access-restriction.spec.ts (pages 36-38)
-- =============================================

-- Page 36: Parent for restricted editor tests
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (36, 1, 'Restricted Section', '/restricted-section', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 0, 1, 3, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `slug_locked` = 0, `perms_userid` = 1, `perms_groupid` = 3, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 0;

-- Page 37: Synced page that restricted editor can see but not toggle sync
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (37, 36, 'Synced No Toggle', '/restricted-section/synced-no-toggle', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1, 0, 1, 3, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 1, `slug_locked` = 0, `perms_userid` = 1, `perms_groupid` = 3, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 0;

-- Page 38: Locked page that restricted editor can see but not toggle lock
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (38, 36, 'Locked No Toggle', '/restricted-section/locked-no-toggle', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 1, 3, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `slug_locked` = 1, `perms_userid` = 1, `perms_groupid` = 3, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 0;

-- =============================================
-- full-path-editing.spec.ts (uses pages 39-42)
-- =============================================
-- Page 39: Full path toggle test (matches hierarchy)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (39, 18, 'Full Path Test', '/parent-section/full-path-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 2, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 0;

-- Page 40: Full path save test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (40, 18, 'Full Path Save', '/parent-section/full-path-save', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 2, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 0;

-- Page 41: Regenerate test - slug matches hierarchy
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (41, 18, 'Regen Match', '/parent-section/regen-match', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 2, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 0;

-- Page 42: Regenerate test - slug was shortened (doesn't match hierarchy)
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (42, 18, 'Short URL Page', '/short-url', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 2, 31, 31, 0)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 2, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 0;

-- =============================================
-- collapsed-controls.spec.ts (uses pages 43, 44)
-- =============================================
-- Page 43: Single-record collapsed controls test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (43, 1, 'Collapsed Controls Test', '/collapsed-controls-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `slug_locked` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 44: Second page for multi-edit test
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `slug_locked`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (44, 1, 'Multi Edit Test', '/multi-edit-test', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1, 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 1, `slug_locked` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- =============================================
-- redirect-control-multi-edit.spec.ts (uses pages 45-48)
-- =============================================
-- Page 45: Parent page for multi-edit redirect tests
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (45, 1, 'Multi Edit Parent', '/multi-edit-parent', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 46: Child 1 for multi-edit redirect tests
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (46, 45, 'Multi Edit Child 1', '/multi-edit-parent/child-1', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 47: Child 2 for multi-edit redirect tests
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (47, 45, 'Multi Edit Child 2', '/multi-edit-parent/child-2', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;

-- Page 48: Child 3 for multi-edit redirect tests
INSERT INTO `pages` (`uid`, `pid`, `title`, `slug`, `doktype`, `is_siteroot`, `hidden`, `deleted`, `tstamp`, `crdate`, `tx_sluggi_sync`, `perms_userid`, `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`)
VALUES (48, 45, 'Multi Edit Child 3', '/multi-edit-parent/child-3', 1, 0, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 1, 0, 31, 31, 31)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `slug` = VALUES(`slug`), `tx_sluggi_sync` = 0, `perms_userid` = 1, `perms_groupid` = 0, `perms_user` = 31, `perms_group` = 31, `perms_everybody` = 31;
