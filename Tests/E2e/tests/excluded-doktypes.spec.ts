import { test, expect } from '@playwright/test';
import { waitForEditForm, waitForEditFormWithoutSlug } from '../fixtures/typo3-compat';

test.describe('Excluded Doktypes - Slug Field Visibility', () => {
  test('slug field is hidden for SysFolder (excluded doktype) and visible for standard page', async ({ page }) => {
    // First verify standard page (2) shows the slug field
    await page.goto('/typo3/record/edit?edit[pages][2]=edit');
    let frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);
    await expect(frame.locator('sluggi-element')).toBeVisible();

    // Now verify SysFolder (29) does NOT show the slug field
    await page.goto('/typo3/record/edit?edit[pages][29]=edit');
    frame = page.frameLocator('iframe');
    await waitForEditFormWithoutSlug(frame, page, 'News Records');
    await expect(frame.locator('sluggi-element')).not.toBeVisible();

    // Save the SysFolder and verify it still works (no errors, slug cleared silently)
    await frame.getByRole('button', { name: 'Save' }).click();
    await page.waitForURL(/edit/, { timeout: 10000 });

    frame = page.frameLocator('iframe');
    await waitForEditFormWithoutSlug(frame, page, 'News Records');
    await expect(frame.locator('sluggi-element')).not.toBeVisible();
  });
});
