import { test, expect } from '@playwright/test';
import { waitForEditForm } from '../fixtures/typo3-compat';

test.describe('Source Badge Fallback Chain - TYPO3 Integration', () => {
  test.use({
    extraHTTPHeaders: {
      'X-Playwright-Test-Id': 'source-badge-fallback-chain',
    },
  });

  test('source badge is visible on nav_title when it is the preferred slug source', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][62]=edit');
    const frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);

    const slugElement = frame.locator('sluggi-element');
    const syncToggle = slugElement.locator('.sluggi-sync-toggle');
    await expect(syncToggle).toHaveClass(/is-synced/);

    const navTitleInput = frame.locator('input[data-formengine-input-name*="[nav_title]"]');
    await expect(navTitleInput).toBeVisible({ timeout: 10000 });
    await expect(navTitleInput).toHaveAttribute('data-sluggi-source', '', { timeout: 10000 });

    const navTitleSourceGroup = navTitleInput.locator('xpath=ancestor::div[contains(concat(" ", normalize-space(@class), " "), " sluggi-source-group ")][1]');
    await expect(navTitleSourceGroup).toHaveClass(/sluggi-source-group--active/);

    const navTitleBadge = navTitleSourceGroup.locator('.sluggi-source-badge');
    await expect(navTitleBadge).toBeVisible({ timeout: 5000 });
    await expect(navTitleBadge).toHaveAttribute('title', /priority 1, used if filled/);
  });

  test('changing nav_title regenerates the synced slug from the preferred source', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][62]=edit');
    const frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);

    const slugElement = frame.locator('sluggi-element');
    const syncToggle = slugElement.locator('.sluggi-sync-toggle');
    await expect(syncToggle).toHaveClass(/is-synced/);

    const slugDisplay = slugElement.locator('.sluggi-editable');
    await expect(slugDisplay).toContainText('/fallback-navigation-title');

    const navTitleInput = frame.locator('input[data-formengine-input-name*="[nav_title]"]');
    await expect(navTitleInput).toHaveAttribute('data-sluggi-source', '', { timeout: 10000 });

    await navTitleInput.fill('Super Test');
    await navTitleInput.blur();

    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 });

    await expect(slugDisplay).toContainText('/super-test', { timeout: 5000 });
  });
});
