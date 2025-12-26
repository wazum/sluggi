import { test, expect } from '@playwright/test';

test.describe('Excluded Doktypes - Slug Field Visibility', () => {
  test('slug field is hidden for SysFolder (excluded doktype) and visible for standard page', async ({ page }) => {
    // First verify standard page (2) shows the slug field
    await page.goto('/typo3/record/edit?edit[pages][2]=edit');
    let frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    await expect(frame.locator('sluggi-element')).toBeVisible();

    // Now verify SysFolder (29) does NOT show the slug field
    await page.goto('/typo3/record/edit?edit[pages][29]=edit');
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('News Records', { timeout: 15000 });
    await expect(frame.locator('sluggi-element')).not.toBeVisible();

    // Save the SysFolder and verify it still works (no errors, slug cleared silently)
    await frame.getByRole('button', { name: 'Save' }).click();
    await page.waitForURL(/edit/, { timeout: 10000 });

    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('News Records', { timeout: 15000 });
    await expect(frame.locator('sluggi-element')).not.toBeVisible();
  });
});
