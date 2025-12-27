import { test as setup, expect } from '@playwright/test';
import path from 'path';

const authFile = path.join(__dirname, '../.auth/editor.json');

setup('authenticate as editor', async ({ page }) => {
  const username = process.env.TYPO3_EDITOR_USER || 'editor';
  const password = process.env.TYPO3_EDITOR_PASS || 'docker';

  await page.goto('/typo3');

  await page.locator('#t3-username').fill(username);
  await page.locator('#t3-password').fill(password);
  await page.locator('#t3-login-submit').click();

  await expect(page.locator('.modulemenu')).toBeVisible({ timeout: 15000 });

  await page.context().storageState({ path: authFile });
});
