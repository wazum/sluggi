import { test, expect } from '@playwright/test';

test.use({ storageState: { cookies: [], origins: [] } });

test('login page loads no sluggi assets', async ({ page }) => {
  const requestedUrls: string[] = [];
  page.on('request', (request) => requestedUrls.push(request.url()));

  await page.goto('/typo3/');
  await expect(page.locator('#t3-login-submit')).toBeVisible();
  await page.waitForLoadState('networkidle');

  const sluggiAssets = requestedUrls.filter((url) =>
    /redirect-notification-handler\.js|page-wizard-guard\.js|sluggi-source-field\.css/.test(url)
  );
  expect(sluggiAssets).toEqual([]);
});
