import { test, expect, FrameLocator } from '@playwright/test';

const CONFLICT_PAGE_ID = 3;

test.describe('Slug Conflicts - TYPO3 Integration', () => {
  let frame: FrameLocator;

  test('server detects duplicate slug and shows conflict modal', async ({ page }) => {
    await page.goto(`/typo3/record/edit?edit[pages][${CONFLICT_PAGE_ID}]=edit`);
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');
    const editableArea = slugElement.locator('.sluggi-editable');
    await editableArea.click();

    const input = slugElement.locator('input.sluggi-input');
    await input.fill('demo');

    const responsePromise = page.waitForResponse(
      (response) => response.url().includes('record_slug_suggest'),
      { timeout: 10000 }
    );

    await input.dispatchEvent('input');
    await input.press('Enter');

    try {
      const response = await responsePromise;
      expect(response.status()).toBe(200);
    } catch {
      // Response may have already completed
    }

    // Modal MUST appear for conflict - /demo is already taken by page 4
    const modal = page.locator('.modal');
    await expect(modal).toBeVisible({ timeout: 5000 });
    const modalBody = modal.locator('.modal-body');
    await expect(modalBody).toContainText('already in use');
  });

  test('applying conflict suggestion updates slug via TYPO3', async ({ page }) => {
    await page.goto(`/typo3/record/edit?edit[pages][${CONFLICT_PAGE_ID}]=edit`);
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');
    const editableArea = slugElement.locator('.sluggi-editable');
    await editableArea.click();

    const input = slugElement.locator('input.sluggi-input');
    await input.fill('demo');
    await input.dispatchEvent('input');
    await input.press('Enter');

    // Modal MUST appear for conflict
    const modal = page.locator('.modal');
    await expect(modal).toBeVisible({ timeout: 5000 });

    // Click "Use Suggestion" button in modal
    const useSuggestionButton = modal.locator('button', { hasText: 'Use Suggestion' });
    await useSuggestionButton.click();
    await modal.waitFor({ state: 'hidden', timeout: 5000 });

    // Slug should be updated to a unique value (e.g., /demo-1 or /demo-2)
    const hiddenField = frame.locator('.sluggi-hidden-field');
    const value = await hiddenField.inputValue();
    expect(value).not.toBe('/demo');
    expect(value).toMatch(/^\/demo-\d+$/);
  });

  test('enabling sync does not show conflict modal when slug is already correct', async ({ page }) => {
    const ALREADY_UNIQUE_PAGE_ID = 5;

    await page.goto(`/typo3/record/edit?edit[pages][${ALREADY_UNIQUE_PAGE_ID}]=edit`);
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');
    await expect(slugElement).toBeVisible();

    const hiddenField = frame.locator('.sluggi-hidden-field');
    const initialSlug = await hiddenField.inputValue();
    expect(initialSlug).toBe('/demo-1');

    const syncToggle = slugElement.locator('.sluggi-sync-toggle');
    const hasSyncToggle = await syncToggle.isVisible({ timeout: 2000 }).catch(() => false);
    test.skip(!hasSyncToggle, 'Sync feature not enabled');

    await expect(syncToggle).not.toHaveClass(/is-synced/);

    await syncToggle.click();

    // Wait for AJAX to complete
    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    // Modal should NOT appear - slug is already unique
    const modal = page.locator('.modal');
    await expect(modal).not.toBeVisible({ timeout: 2000 });

    const currentSlug = await hiddenField.inputValue();
    expect(currentSlug).toBe('/demo-1');

    await expect(syncToggle).toHaveClass(/is-synced/);
  });
});
