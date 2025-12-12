import { test as setup, expect } from '@playwright/test';
import path from 'path';

const authFile = path.join(__dirname, '../.auth/editor.json');

setup('authenticate as editor', async ({ page }) => {
  const username = process.env.TYPO3_EDITOR_USER || 'editor';
  const password = process.env.TYPO3_EDITOR_PASS || 'docker';

  await page.goto('/typo3');

  await page.getByLabel('Username').fill(username);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Login' }).click();

  await expect(page.locator('.modulemenu')).toBeVisible({ timeout: 15000 });

  await page.context().storageState({ path: authFile });
});
