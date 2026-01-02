import { test as setup, expect } from '@playwright/test';
import path from 'path';

const authFile = path.join(__dirname, '../.auth/collapsed-admin.json');

setup('authenticate collapsed admin', async ({ page }) => {
  const username = 'collapsed_admin';
  const password = process.env.TYPO3_ADMIN_PASS || 'docker';

  await page.goto('/typo3');

  await page.locator('#t3-username').fill(username);
  await page.locator('#t3-password').fill(password);
  await page.locator('#t3-login-submit').click();

  await expect(page.locator('.modulemenu')).toBeVisible({ timeout: 15000 });

  await page.context().storageState({ path: authFile });
});
