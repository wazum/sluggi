-- Puts the seeded pending translations (database.sql, pages 74-77) back to their
-- placeholder state, so a test that saves one can run again.
UPDATE `pages` SET
  `title` = '[Translate to German:] Pending Preview Source',
  `slug` = '/restricted-section/translate-to-german-pending-preview-source',
  `tx_sluggi_slug_pending` = 1
WHERE `uid` = 75;

UPDATE `pages` SET
  `title` = '[Translate to German:] Pending Confirm Source',
  `slug` = '/restricted-section/translate-to-german-pending-confirm-source',
  `tx_sluggi_slug_pending` = 1
WHERE `uid` = 77;
