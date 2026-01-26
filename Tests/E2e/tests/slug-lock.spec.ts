import { test, expect } from '@playwright/test';
import { waitForPageTree, clickModuleMenuItem } from '../fixtures/typo3-compat';

test.describe('Slug Lock Toggle - TYPO3 Integration', () => {
  test.use({
    extraHTTPHeaders: {
      'X-Playwright-Test-Id': 'slug-lock',
    },
  });

  test('lock toggle button is visible with label', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][30]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');
    const lockWrapper = slugElement.locator('.sluggi-lock-wrapper');
    await expect(lockWrapper).toBeVisible();

    const lockLabel = lockWrapper.locator('.sluggi-lock-label');
    await expect(lockLabel).toHaveText('lock');

    const lockToggle = lockWrapper.locator('.sluggi-lock-toggle');
    await expect(lockToggle).toBeVisible();
  });

  test('clicking lock toggle changes visual state', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][31]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');

    const lockToggle = slugElement.locator('.sluggi-lock-toggle');
    await expect(lockToggle).not.toHaveClass(/is-locked/);

    await lockToggle.click();

    await expect(lockToggle).toHaveClass(/is-locked/);
  });

  test('locked slug field shows lock icon and is not editable', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][33]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');

    const lockToggle = slugElement.locator('.sluggi-lock-toggle');
    await expect(lockToggle).toHaveClass(/is-locked/);

    const wrapper = slugElement.locator('.sluggi-wrapper');
    await expect(wrapper).toHaveClass(/locked/);

    const editable = slugElement.locator('.sluggi-editable');
    await expect(editable).toHaveClass(/locked/);
  });

  test('lock state persists after form save and full page reload', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][32]=edit');
    let frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    let slugElement = frame.locator('sluggi-element');
    let lockToggle = slugElement.locator('.sluggi-lock-toggle');

    await expect(lockToggle).not.toHaveClass(/is-locked/);

    await lockToggle.click();
    await expect(lockToggle).toHaveClass(/is-locked/);

    await frame.locator('button[name="_savedok"]').click();
    await page.waitForURL(/edit/, { timeout: 10000 });

    await page.goto('/typo3/module/web/layout');
    await waitForPageTree(page, 10000);

    await page.goto('/typo3/record/edit?edit[pages][32]=edit');
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    slugElement = frame.locator('sluggi-element');
    lockToggle = slugElement.locator('.sluggi-lock-toggle');

    await expect(lockToggle).toHaveClass(/is-locked/);
  });

  test('sync toggle is disabled when page is locked', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][33]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');

    const lockToggle = slugElement.locator('.sluggi-lock-toggle');
    await expect(lockToggle).toHaveClass(/is-locked/);

    const syncToggle = slugElement.locator('.sluggi-sync-toggle');
    await expect(syncToggle).toBeVisible();
    await expect(syncToggle).toHaveClass(/is-disabled/);
    await expect(syncToggle).toBeDisabled();
  });

  test('locking page disables sync toggle', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][34]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');

    const syncToggle = slugElement.locator('.sluggi-sync-toggle');
    const lockToggle = slugElement.locator('.sluggi-lock-toggle');
    await expect(syncToggle).toBeVisible();
    await expect(syncToggle).not.toHaveClass(/is-disabled/);
    await expect(lockToggle).toBeVisible();

    await lockToggle.click();
    await expect(lockToggle).toHaveClass(/is-locked/);

    await expect(syncToggle).toBeVisible();
    await expect(syncToggle).toHaveClass(/is-disabled/);
    await expect(syncToggle).toBeDisabled();
  });

  test('synced page disables lock toggle', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][35]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');

    const syncToggle = slugElement.locator('.sluggi-sync-toggle');
    await expect(syncToggle).toBeVisible();
    await expect(syncToggle).toHaveClass(/is-synced/);

    const lockToggle = slugElement.locator('.sluggi-lock-toggle');
    await expect(lockToggle).toBeVisible();
    await expect(lockToggle).toHaveClass(/is-disabled/);
    await expect(lockToggle).toBeDisabled();
  });

  test('toggling lock marks form as dirty and shows unsaved changes modal', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][30]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');
    const lockToggle = slugElement.locator('.sluggi-lock-toggle');

    await expect(lockToggle).not.toHaveClass(/is-locked/);

    await lockToggle.click();
    await expect(lockToggle).toHaveClass(/is-locked/);

    await clickModuleMenuItem(page, 'Layout', 'web_layout');

    const modal = page.locator('.modal');
    await expect(modal).toBeVisible({ timeout: 5000 });
    await expect(modal).toContainText(/unsaved/i);

    await modal.locator('button[name="no"]').click();
    await expect(modal).not.toBeVisible();
  });
});
