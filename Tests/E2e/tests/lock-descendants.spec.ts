import { test, expect } from '@playwright/test';
import { waitForEditForm } from '../fixtures/typo3-compat';

test.describe('Lock Descendants - TYPO3 Integration', () => {
  test.use({
    extraHTTPHeaders: {
      'X-Playwright-Test-Id': 'lock-descendants',
    },
  });

  test('child of a locked page is shown as locked without a lock of its own', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][72]=edit');
    const frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);
    const slugElement = frame.locator('sluggi-element');

    await expect(slugElement.locator('.sluggi-wrapper')).toHaveClass(/locked/);
    await expect(slugElement.locator('.sluggi-editable')).toHaveClass(/locked/);
    await expect(slugElement.locator('.sluggi-note')).toContainText('locked by a parent page');

    // The page's own lock is untouched — only the ancestor is locked.
    await expect(frame.locator('input.sluggi-lock-field')).toHaveValue('0');
  });

  test('lock toggle of an ancestor-locked page is locked but disabled', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][72]=edit');
    const frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);
    const lockToggle = frame.locator('sluggi-element .sluggi-lock-toggle');

    await expect(lockToggle).toBeVisible();
    await expect(lockToggle).toHaveClass(/is-locked/);
    await expect(lockToggle).toHaveClass(/is-disabled/);
    await expect(lockToggle).toBeDisabled();
    await expect(lockToggle).toHaveAttribute('title', /parent page/);
  });

  test('grandchild of a locked page is locked as well', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][73]=edit');
    const frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);
    const slugElement = frame.locator('sluggi-element');

    await expect(slugElement.locator('.sluggi-editable')).toHaveClass(/locked/);
    await expect(frame.locator('input.sluggi-lock-field')).toHaveValue('0');
  });

  test('saving an ancestor-locked page keeps its URL path and its own unlocked state', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][72]=edit');
    let frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);

    await frame.locator('button[name="_savedok"]').click();
    await page.waitForURL(/edit/, { timeout: 10000 });

    await page.goto('/typo3/record/edit?edit[pages][72]=edit');
    frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);

    await expect(frame.locator('input.sluggi-hidden-field')).toHaveValue('/descendant-lock-parent/child');
    await expect(frame.locator('input.sluggi-lock-field')).toHaveValue('0');
  });

  test('page outside the locked subtree stays editable', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][30]=edit');
    const frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);
    const slugElement = frame.locator('sluggi-element');

    await expect(slugElement.locator('.sluggi-editable')).not.toHaveClass(/locked/);

    const lockToggle = slugElement.locator('.sluggi-lock-toggle');
    await expect(lockToggle).not.toHaveClass(/is-locked/);
    await expect(lockToggle).toBeEnabled();
  });
});
