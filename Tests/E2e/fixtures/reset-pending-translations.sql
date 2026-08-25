-- Puts the seeded pending translations (see database.sql, pages 74-77) back into the
-- state a fresh "Translate" leaves behind. Confirming the lock dialog spends the
-- one-shot window, so any test that saves such a translation has to re-arm it — for
-- itself and for its retries. The locked source pages 74/76 are read-only and stay.
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
