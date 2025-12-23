import { test, expect } from '@playwright/test';

const SYSFOLDER_PAGE_ID = 27;
const STANDARD_PAGE_ID = 2;

test.describe('Excluded Doktypes - Slug Field Visibility', () => {
  test('slug field is hidden for SysFolder (excluded doktype) and visible for standard page', async ({ page }) => {
    // First verify standard page shows the slug field
    await page.goto(`/typo3/record/edit?edit[pages][${STANDARD_PAGE_ID}]=edit`);
    let frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    await expect(frame.locator('sluggi-element')).toBeVisible();

    // Now verify SysFolder does NOT show the slug field
    await page.goto(`/typo3/record/edit?edit[pages][${SYSFOLDER_PAGE_ID}]=edit`);
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
