import { test, expect } from '@playwright/test';
import { waitForPageTree, clickModuleMenuItem } from '../fixtures/typo3-compat';

test.describe('Slug Sync Toggle - TYPO3 Integration', () => {
  test('sync toggle button is visible with label', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][15]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');
    const syncWrapper = slugElement.locator('.sluggi-sync-wrapper');
    await expect(syncWrapper).toBeVisible();

    const syncLabel = syncWrapper.locator('.sluggi-sync-label');
    await expect(syncLabel).toHaveText('sync');

    const syncToggle = syncWrapper.locator('.sluggi-sync-toggle');
    await expect(syncToggle).toBeVisible();
  });

  test('clicking sync toggle changes visual state', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][7]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');

    const syncToggle = slugElement.locator('.sluggi-sync-toggle');
    await expect(syncToggle).not.toHaveClass(/is-synced/);

    await syncToggle.click();
    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    await expect(syncToggle).toHaveClass(/is-synced/);
  });

  test('enabling sync triggers slug regeneration', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][9]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');

    const syncToggle = slugElement.locator('.sluggi-sync-toggle');
    await expect(syncToggle).not.toHaveClass(/is-synced/);

    await syncToggle.click();

    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 });

    await expect(syncToggle).toHaveClass(/is-synced/);
  });

  test('sync toggle remains visible when sync is off (regression)', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][11]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');
    const syncToggle = slugElement.locator('.sluggi-sync-toggle');

    await expect(syncToggle).not.toHaveClass(/is-synced/);
    await expect(syncToggle).toBeVisible();
  });

  test('source badge is hidden when sync is off', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][12]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const sourceBadge = frame.locator('.sluggi-source-badge');
    await expect(sourceBadge.first()).toBeAttached();
    await expect(sourceBadge.first()).not.toBeVisible();
  });

  test('source badge is visible when sync is on', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][13]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');
    const syncToggle = slugElement.locator('.sluggi-sync-toggle');

    await syncToggle.click();
    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    const sourceBadge = frame.locator('.sluggi-source-badge');
    await expect(sourceBadge.first()).toBeVisible({ timeout: 5000 });
  });

  test('source badge appears immediately when toggling sync on (no reload)', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][10]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');

    const syncToggle = slugElement.locator('.sluggi-sync-toggle');
    await expect(syncToggle).toBeVisible();
    await expect(syncToggle).not.toHaveClass(/is-synced/);

    const sourceBadge = frame.locator('.sluggi-source-badge');
    await expect(sourceBadge.first()).toBeAttached();
    await expect(sourceBadge.first()).not.toBeVisible();

    await syncToggle.click();
    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    await expect(sourceBadge.first()).toBeVisible({ timeout: 5000 });
  });

  test('sync state persists after form save and full page reload', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][14]=edit');
    let frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    let slugElement = frame.locator('sluggi-element');
    let syncToggle = slugElement.locator('.sluggi-sync-toggle');

    await expect(syncToggle).not.toHaveClass(/is-synced/);

    await syncToggle.click();
    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    await expect(syncToggle).toHaveClass(/is-synced/);

    await frame.locator('button[name="_savedok"]').click();
    await page.waitForURL(/edit/, { timeout: 10000 });

    await page.goto('/typo3/module/web/layout');
    await waitForPageTree(page, 10000);

    await page.goto('/typo3/record/edit?edit[pages][14]=edit');
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    slugElement = frame.locator('sluggi-element');
    syncToggle = slugElement.locator('.sluggi-sync-toggle');

    await expect(syncToggle).toHaveClass(/is-synced/);
  });

  test('toggling sync marks form as dirty and shows unsaved changes modal', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][16]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');
    const syncToggle = slugElement.locator('.sluggi-sync-toggle');

    await expect(syncToggle).not.toHaveClass(/is-synced/);

    await syncToggle.click();
    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});
    await expect(syncToggle).toHaveClass(/is-synced/);

    await clickModuleMenuItem(page, 'Layout', 'web_layout');

    const modal = page.locator('.modal');
    await expect(modal).toBeVisible({ timeout: 5000 });
    await expect(modal).toContainText(/unsaved/i);

    await modal.locator('button[name="no"]').click();
    await expect(modal).not.toBeVisible();
  });
});
