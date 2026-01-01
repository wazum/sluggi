import {expect, FrameLocator, Locator, test} from '@playwright/test';
import { waitForFormFrame } from '../fixtures/typo3-compat';

test.describe('Full Path Editing - Editor Button', () => {
  let frame: FrameLocator;
  let slugElement: Locator;

  test.use({
    extraHTTPHeaders: {
      'X-Playwright-Test-ID': 'last-segment-only',
    },
  });

  test.beforeEach(async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][39]=edit');
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    slugElement = frame.locator('sluggi-element');
  });

  test('full path edit button is visible when restrictions apply', async () => {
    await expect(slugElement).toBeVisible();
    await expect(slugElement).toHaveAttribute('last-segment-only', '');
    await expect(slugElement).toHaveAttribute('full-path-feature-enabled', '');

    const fullPathEditBtn = slugElement.locator('.sluggi-full-path-edit-btn');
    await expect(fullPathEditBtn).toBeVisible();
  });

  test('clicking full path edit button enters edit mode and removes prefix', async () => {
    const prefix = slugElement.locator('.sluggi-prefix');
    await expect(prefix).toBeVisible();
    await expect(prefix).toContainText('/parent-section');

    const fullPathEditBtn = slugElement.locator('.sluggi-full-path-edit-btn');
    await fullPathEditBtn.click();

    const input = slugElement.locator('input.sluggi-input');
    await expect(input).toBeVisible();
    await expect(prefix).not.toBeVisible();
  });

  test('slashes are allowed in input when using full path edit', async () => {
    const fullPathEditBtn = slugElement.locator('.sluggi-full-path-edit-btn');
    await fullPathEditBtn.click();

    const input = slugElement.locator('input.sluggi-input');
    await input.fill('new-parent/new-segment');
    await input.press('Enter');

    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    const hiddenField = frame.locator('.sluggi-hidden-field');
    const value = await hiddenField.inputValue();
    expect(value).toContain('/new-parent/new-segment');
  });

  test('dashes are preserved in full path mode when typing', async () => {
    const fullPathEditBtn = slugElement.locator('.sluggi-full-path-edit-btn');
    await fullPathEditBtn.click();

    const input = slugElement.locator('input.sluggi-input');
    await input.clear();
    await input.type('my-parent/my-segment');

    const inputValue = await input.inputValue();
    expect(inputValue).toBe('my-parent/my-segment');
  });

  test('full path change persists after save', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][40]=edit');
    const editFrame = page.frameLocator('iframe');
    await expect(editFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugEl = editFrame.locator('sluggi-element');
    const fullPathEditBtn = slugEl.locator('.sluggi-full-path-edit-btn');
    await fullPathEditBtn.click();

    const input = slugEl.locator('input.sluggi-input');
    await input.fill('custom-path/nested-page');
    await input.press('Enter');

    await slugEl.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    await editFrame.locator('button[name="_savedok"]').click();
    await page.waitForURL(/edit/, { timeout: 10000 });

    const savedFrame = page.frameLocator('iframe');
    await expect(savedFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const savedHiddenField = savedFrame.locator('.sluggi-hidden-field');
    await expect(savedHiddenField).toHaveValue('/custom-path/nested-page');
  });

  test('full path edit button is disabled when slug is synced', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][22]=edit');
    const editFrame = page.frameLocator('iframe');
    await expect(editFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugEl = editFrame.locator('sluggi-element');
    const syncToggle = slugEl.locator('.sluggi-sync-toggle');

    if (!(await syncToggle.evaluate((el) => el.classList.contains('is-synced')))) {
      await syncToggle.click();
    }
    await expect(syncToggle).toHaveClass(/is-synced/);

    const fullPathEditBtn = slugEl.locator('.sluggi-full-path-edit-btn');
    await expect(fullPathEditBtn).toBeVisible();
    await expect(fullPathEditBtn).toHaveClass(/is-disabled/);
    await expect(fullPathEditBtn).toBeDisabled();
  });

  test('using full path edit marks form as dirty', async ({ page }) => {
    const fullPathEditBtn = slugElement.locator('.sluggi-full-path-edit-btn');
    await fullPathEditBtn.click();

    const input = slugElement.locator('input.sluggi-input');
    await input.press('Escape');

    await page.click('.scaffold-modulemenu [data-modulemenu-identifier="web_layout"]');

    const modal = page.locator('.modal');
    await expect(modal).toBeVisible({ timeout: 5000 });
    await expect(modal).toContainText(/unsaved/i);

    await modal.locator('button[name="no"]').click();
    await expect(modal).not.toBeVisible();
  });
});

test.describe('Full Path Editing - Regenerate Behavior', () => {
  test.use({
    extraHTTPHeaders: {
      'X-Playwright-Test-ID': 'last-segment-only',
    },
  });

  test('regenerate only updates last segment when slug matches hierarchy', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][41]=edit');
    const frame = await waitForFormFrame(page);

    const slugElement = frame.locator('sluggi-element');

    const regenerateBtn = slugElement.locator('.sluggi-regenerate-btn');
    await expect(regenerateBtn).toBeVisible({ timeout: 10000 });
    await regenerateBtn.click();

    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    await frame.locator('button[name="_savedok"]').click();
    await page.waitForURL(/edit/, { timeout: 10000 });

    const editFrame = page.frameLocator('iframe');
    await expect(editFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const errorAlert = editFrame.locator('.alert-danger');
    await expect(errorAlert).not.toBeVisible();
  });

  test('regenerate with auto-activated full path saves successfully', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][42]=edit');
    const frame = await waitForFormFrame(page);

    const slugElement = frame.locator('sluggi-element');
    const regenerateBtn = slugElement.locator('.sluggi-regenerate-btn');
    await expect(regenerateBtn).toBeVisible({ timeout: 10000 });
    await regenerateBtn.click();

    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    await frame.locator('button[name="_savedok"]').click();
    await page.waitForURL(/edit/, { timeout: 10000 });

    const editFrame = page.frameLocator('iframe');
    await expect(editFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const errorAlert = editFrame.locator('.alert-danger');
    await expect(errorAlert).not.toBeVisible();

    const hiddenField = editFrame.locator('.sluggi-hidden-field');
    const slugValue = await hiddenField.inputValue();
    expect(slugValue).toContain('/');
    expect(slugValue).not.toBe('/short-url');
  });
});
