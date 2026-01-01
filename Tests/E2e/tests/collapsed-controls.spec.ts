import { test, expect } from '@playwright/test';
import { getMultiEditUrl } from '../fixtures/typo3-compat';

test.describe('Collapsed Controls', () => {
  test.use({
    extraHTTPHeaders: {
      'X-Playwright-Test-ID': 'collapsed-controls',
    },
  });

  test.describe('single-record edit mode', () => {
    test('burger menu trigger is visible and controls are hidden', async ({ page }) => {
      await page.goto('/typo3/record/edit?edit[pages][43]=edit');
      const frame = page.frameLocator('iframe');
      await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

      const slugElement = frame.locator('sluggi-element');
      const menuTrigger = slugElement.locator('.sluggi-menu-trigger');
      const menuContent = slugElement.locator('.sluggi-menu-content');

      await expect(menuTrigger).toBeVisible();
      await expect(menuContent).not.toBeVisible();
    });

    test('hovering expands controls', async ({ page }) => {
      await page.goto('/typo3/record/edit?edit[pages][43]=edit');
      const frame = page.frameLocator('iframe');
      await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

      const slugElement = frame.locator('sluggi-element');
      const wrapper = slugElement.locator('.sluggi-wrapper');
      const menuContent = slugElement.locator('.sluggi-menu-content');

      await expect(menuContent).not.toBeVisible();

      await wrapper.hover();

      await expect(menuContent).toBeVisible({ timeout: 1000 });
    });

    test('controls retract after mouse leaves', async ({ page }) => {
      await page.goto('/typo3/record/edit?edit[pages][43]=edit');
      const frame = page.frameLocator('iframe');
      await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

      const slugElement = frame.locator('sluggi-element');
      const wrapper = slugElement.locator('.sluggi-wrapper');
      const menuContent = slugElement.locator('.sluggi-menu-content');
      const menuTrigger = slugElement.locator('.sluggi-menu-trigger');

      await wrapper.hover();
      await expect(menuContent).toBeVisible({ timeout: 1000 });

      await frame.locator('h1').hover();
      await page.waitForTimeout(2500);

      await expect(menuContent).not.toBeVisible();
      await expect(menuTrigger).toBeVisible();
    });
  });

  test.describe('multi-edit mode', () => {
    test('controls are always visible without burger menu', async ({ page }) => {
      // First navigate to detect TYPO3 version, then get proper multi-edit URL
      await page.goto('/typo3');
      const multiEditUrl = await getMultiEditUrl(page, 'pages', [43, 44], ['slug']);
      await page.goto(multiEditUrl);

      const frame = page.frameLocator('iframe');
      // Multi-edit shows multiple h1 headings (one per record)
      await expect(frame.locator('h1').first()).toContainText('Edit Page', { timeout: 15000 });

      const slugElement = frame.locator('sluggi-element').first();
      const menuTrigger = slugElement.locator('.sluggi-menu-trigger');
      const controls = slugElement.locator('.sluggi-controls');

      await expect(menuTrigger).not.toBeVisible();
      await expect(controls).toBeVisible();
    });
  });
});
