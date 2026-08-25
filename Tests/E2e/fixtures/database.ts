import { execFileSync } from 'child_process';
import path from 'path';

/**
 * Applies one of the .sql files next to this helper to the test database.
 *
 * Connection details come from the environment so the same call works in the DDEV
 * container (defaults below) and in CI, which talks to its own MySQL service.
 */
export function applyFixtureSql(fileName: string): void {
  const file = path.join(__dirname, fileName);
  const host = process.env.TYPO3_DB_HOST || 'db';
  const user = process.env.TYPO3_DB_USER || 'db';
  const password = process.env.TYPO3_DB_PASSWORD || 'db';
  const database = process.env.TYPO3_DB_NAME || 'db';

  execFileSync(
    'mysql',
    [`-h${host}`, `-u${user}`, `-p${password}`, database, '-e', `source ${file}`],
    { stdio: 'pipe' },
  );
}

/**
 * Re-arms the seeded pending translations. Saving one spends its one-shot window, so
 * every test that gets that far has to put the state back — otherwise it passes once
 * and fails on the next run and on its own retries.
 */
export function resetPendingTranslations(): void {
  applyFixtureSql('reset-pending-translations.sql');
}
