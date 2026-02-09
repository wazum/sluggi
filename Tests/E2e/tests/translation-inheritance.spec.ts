import { test, expect } from '@playwright/test';

test.describe('Translation Inheritance - Sync/Lock from Default Language', () => {
  test('sync toggle on translation is disabled and shows inherited state from default language', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][52]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');
    const syncToggle = slugElement.locator('.sluggi-sync-toggle');

    await expect(syncToggle).toBeVisible();
    await expect(syncToggle).toHaveClass(/is-synced/);
    await expect(syncToggle).toBeDisabled();
  });

  test('lock toggle on translation is disabled and shows inherited state from default language', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][54]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');
    const lockToggle = slugElement.locator('.sluggi-lock-toggle');

    await expect(lockToggle).toBeVisible();
    await expect(lockToggle).toHaveClass(/is-locked/);
    await expect(lockToggle).toBeDisabled();
  });

  test('default language page has editable sync toggle', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][51]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');
    const syncToggle = slugElement.locator('.sluggi-sync-toggle');

    await expect(syncToggle).toBeVisible();
    await expect(syncToggle).toBeEnabled();
    await expect(syncToggle).toHaveClass(/is-synced/);
  });

  test('default language page has editable lock toggle', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][53]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');
    const lockToggle = slugElement.locator('.sluggi-lock-toggle');

    await expect(lockToggle).toBeVisible();
    await expect(lockToggle).toBeEnabled();
    await expect(lockToggle).toHaveClass(/is-locked/);
  });
});
