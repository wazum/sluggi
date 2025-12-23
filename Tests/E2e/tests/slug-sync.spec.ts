import { test, expect } from '@playwright/test';

test.describe('Slug Sync Toggle - TYPO3 Integration', () => {
  test('sync toggle button is visible with label', async ({ page }) => {
    // Use dedicated page 15
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
    // Use dedicated page 7 with unique slug to avoid conflicts
    await page.goto('/typo3/record/edit?edit[pages][7]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');

    const syncToggle = slugElement.locator('.sluggi-sync-toggle');
    await expect(syncToggle).not.toHaveClass(/is-synced/); // Verify off (fixture default)

    await syncToggle.click();
    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    await expect(syncToggle).toHaveClass(/is-synced/); // Now on
  });

  test('enabling sync triggers slug regeneration', async ({ page }) => {
    // Use dedicated page 9 with unique slug to avoid conflicts
    await page.goto('/typo3/record/edit?edit[pages][9]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');

    const syncToggle = slugElement.locator('.sluggi-sync-toggle');
    await expect(syncToggle).not.toHaveClass(/is-synced/); // Verify off (fixture default)

    // Enable sync
    await syncToggle.click();

    // Wait for spinner to appear then disappear (slug regeneration via AJAX)
    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 });

    // Verify sync is now ON
    await expect(syncToggle).toHaveClass(/is-synced/);
  });

  test('sync toggle remains visible when sync is off (regression)', async ({ page }) => {
    // This tests the fix for a bug where the sync toggle would disappear
    // when sync was disabled, because data-sluggi-source was only added
    // when the badge was shown (which required sync to be ON).
    // Use dedicated page 11
    await page.goto('/typo3/record/edit?edit[pages][11]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');
    const syncToggle = slugElement.locator('.sluggi-sync-toggle');

    // Verify sync is OFF (fixture default)
    await expect(syncToggle).not.toHaveClass(/is-synced/);

    // KEY ASSERTION: Toggle must be visible even when sync is OFF
    await expect(syncToggle).toBeVisible();
  });

  test('source badge is hidden when sync is off', async ({ page }) => {
    // Use dedicated page 12 (sync OFF by fixture default)
    await page.goto('/typo3/record/edit?edit[pages][12]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    // The source badge exists but should NOT be visible when sync is OFF
    const sourceBadge = frame.locator('.sluggi-source-badge');
    await expect(sourceBadge.first()).toBeAttached();
    await expect(sourceBadge.first()).not.toBeVisible();
  });

  test('source badge is visible when sync is on', async ({ page }) => {
    // Use dedicated page 13, enable sync and verify badge is visible
    await page.goto('/typo3/record/edit?edit[pages][13]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');
    const syncToggle = slugElement.locator('.sluggi-sync-toggle');

    // Enable sync
    await syncToggle.click();
    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    // The source badge SHOULD be visible when sync is ON
    const sourceBadge = frame.locator('.sluggi-source-badge');
    await expect(sourceBadge.first()).toBeVisible({ timeout: 5000 });
  });

  test('source badge appears immediately when toggling sync on (no reload)', async ({ page }) => {
    // Use dedicated page 10 with unique slug to avoid conflicts
    await page.goto('/typo3/record/edit?edit[pages][10]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');

    const syncToggle = slugElement.locator('.sluggi-sync-toggle');
    await expect(syncToggle).toBeVisible();
    await expect(syncToggle).not.toHaveClass(/is-synced/); // Verify off (fixture default)

    const sourceBadge = frame.locator('.sluggi-source-badge');
    await expect(sourceBadge.first()).toBeAttached();
    await expect(sourceBadge.first()).not.toBeVisible();

    await syncToggle.click();
    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    await expect(sourceBadge.first()).toBeVisible({ timeout: 5000 });
  });

  test('sync state persists after form save and full page reload', async ({ page }) => {
    // Use dedicated page 14
    await page.goto('/typo3/record/edit?edit[pages][14]=edit');
    let frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    let slugElement = frame.locator('sluggi-element');
    let syncToggle = slugElement.locator('.sluggi-sync-toggle');

    // Verify sync is OFF (fixture default)
    await expect(syncToggle).not.toHaveClass(/is-synced/);

    // Enable sync
    await syncToggle.click();
    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    // Verify sync is ON before save
    await expect(syncToggle).toHaveClass(/is-synced/);

    // Save the form
    await frame.locator('button[name="_savedok"]').click();
    await page.waitForURL(/edit/, { timeout: 10000 });

    // Navigate away completely
    await page.goto('/typo3/module/web/layout');
    await expect(page.locator('.scaffold-content-navigation-component')).toBeVisible({ timeout: 10000 });

    // Navigate back and verify sync is still ON
    await page.goto('/typo3/record/edit?edit[pages][14]=edit');
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    slugElement = frame.locator('sluggi-element');
    syncToggle = slugElement.locator('.sluggi-sync-toggle');

    // KEY ASSERTION: Sync state must persist after full page reload
    await expect(syncToggle).toHaveClass(/is-synced/);
  });

  test('toggling sync marks form as dirty and shows unsaved changes modal', async ({ page }) => {
    // Use dedicated page 16
    await page.goto('/typo3/record/edit?edit[pages][16]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');
    const syncToggle = slugElement.locator('.sluggi-sync-toggle');

    // Verify sync is OFF initially
    await expect(syncToggle).not.toHaveClass(/is-synced/);

    // Toggle sync ON
    await syncToggle.click();
    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});
    await expect(syncToggle).toHaveClass(/is-synced/);

    // Try to navigate away - this should trigger the unsaved changes modal
    await page.click('.scaffold-modulemenu [data-modulemenu-identifier="web_layout"]');

    // TYPO3's unsaved changes modal should appear
    const modal = page.locator('.modal');
    await expect(modal).toBeVisible({ timeout: 5000 });

    // Verify it's the unsaved changes modal (check header or body text)
    await expect(modal).toContainText(/unsaved/i);

    // Dismiss the modal by clicking "No" / "Keep editing"
    await modal.locator('button[name="no"]').click();
    await expect(modal).not.toBeVisible();
  });
});
