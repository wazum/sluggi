import { test, expect } from '@playwright/test';

test.describe('Page Create - Sync Default', () => {
  test('new page form has sync enabled by default when global sync is on', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][1]=new');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Create new Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');
    await expect(slugElement).toBeVisible();
    await expect(slugElement).toHaveAttribute('is-synced', '');
  });

  test('sync persists after saving new page', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][1]=new');
    let frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Create new Page', { timeout: 15000 });

    const titleInput = frame.locator('input[data-formengine-input-name*="[title]"]');
    await titleInput.fill('Sync Persist Test');

    await frame.getByRole('button', { name: 'Save' }).click();
    await page.waitForURL(/edit/, { timeout: 10000 });

    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');
    await expect(slugElement).toBeVisible();
    await expect(slugElement).toHaveAttribute('is-synced', '');
  });
});
