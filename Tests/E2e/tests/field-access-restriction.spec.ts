import { test, expect } from '@playwright/test';

test.describe('Field Access Restriction - Restricted Editor', () => {
  test('synced page shows sync indicator and auto-syncs on title change', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][35]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');

    await expect(slugElement.locator('.sluggi-sync-toggle')).not.toBeVisible();
    await expect(slugElement.locator('.sluggi-sync-icon-static')).toBeVisible();

    const hiddenInput = frame.locator('input.sluggi-hidden-field');
    const originalSlug = await hiddenInput.inputValue();
    expect(originalSlug).toBe('/restricted-section/synced-no-toggle');

    const titleInput = frame.locator('input[data-formengine-input-name*="[title]"]');
    await titleInput.fill('New Title For Sync');
    await titleInput.blur();

    await expect(hiddenInput).not.toHaveValue(originalSlug, { timeout: 5000 });
    const newSlug = await hiddenInput.inputValue();
    expect(newSlug).toContain('new-title-for-sync');
  });

  test('locked page shows lock indicator and prevents editing', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][36]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const slugElement = frame.locator('sluggi-element');

    await expect(slugElement.locator('.sluggi-lock-toggle')).not.toBeVisible();
    await expect(slugElement.locator('.sluggi-wrapper')).toHaveClass(/locked/);
    await expect(slugElement.locator('.sluggi-editable')).toHaveClass(/locked/);
  });
});
