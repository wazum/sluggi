import { test, expect } from '@playwright/test';
import { waitForEditForm, waitForSaveSuccess, waitForSourceFieldsInitialized } from '../fixtures/typo3-compat';
import { resetPendingTranslations } from '../fixtures/database';

test.describe('Field Access Restriction - Restricted Editor', () => {
  test('synced page without toggle hides all controls and auto-syncs on title change', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][37]=edit');
    const frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);
    const slugElement = frame.locator('sluggi-element');

    await expect(slugElement.locator('.sluggi-sync-toggle')).not.toBeVisible();
    await expect(slugElement.locator('.sluggi-edit-btn')).not.toBeVisible();
    await expect(slugElement.locator('.sluggi-editable')).toHaveClass(/no-edit/);
    await expect(slugElement.locator('.sluggi-copy-url-btn')).toBeVisible();

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

  test('locked page without toggle hides all controls and prevents editing', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][38]=edit');
    const frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);
    const slugElement = frame.locator('sluggi-element');

    await expect(slugElement.locator('.sluggi-lock-toggle')).not.toBeVisible();
    await expect(slugElement.locator('.sluggi-edit-btn')).not.toBeVisible();
    await expect(slugElement.locator('.sluggi-wrapper')).toHaveClass(/locked/);
    await expect(slugElement.locator('.sluggi-editable')).toHaveClass(/locked/);
    await expect(slugElement.locator('.sluggi-editable')).toHaveClass(/no-edit/);
    await expect(slugElement.locator('.sluggi-copy-url-btn')).toBeVisible();
  });

  test.describe('Pending slug generation', () => {
    test.beforeEach(() => resetPendingTranslations());

    test('previews the URL path from the title', async ({ page }) => {
      await page.goto('/typo3/record/edit?edit[pages][75]=edit');
      const frame = page.frameLocator('iframe');
      await waitForEditForm(frame, page);
      await waitForSourceFieldsInitialized(frame);
      const slugElement = frame.locator('sluggi-element');

      await expect(slugElement).toHaveAttribute('slug-pending', '');
      await expect(slugElement.locator('.sluggi-lock-toggle')).not.toBeVisible();
      await expect(slugElement.locator('.sluggi-note')).toContainText('generated from the source fields');

      const hiddenInput = frame.locator('input.sluggi-hidden-field');
      await expect(hiddenInput).toHaveValue('/restricted-section/translate-to-german-pending-preview-source');

      const titleInput = frame.locator('input[data-formengine-input-name*="[title]"]');
      await titleInput.fill('Vorschau Titel');
      await titleInput.blur();

      // The proposal endpoint has to answer for a locked record, or nobody sees the path.
      await expect(hiddenInput).toHaveValue('/restricted-section/vorschau-titel', { timeout: 10000 });
    });

    test('confirms the generated URL path before locking it, and saves it', async ({ page }) => {
      await page.goto('/typo3/record/edit?edit[pages][77]=edit');
      const frame = page.frameLocator('iframe');
      await waitForEditForm(frame, page);
      await waitForSourceFieldsInitialized(frame);

      const hiddenInput = frame.locator('input.sluggi-hidden-field');
      const titleInput = frame.locator('input[data-formengine-input-name*="[title]"]');
      await titleInput.fill('Bestaetigter Titel');
      await titleInput.blur();
      await expect(hiddenInput).toHaveValue('/restricted-section/bestaetigter-titel', { timeout: 10000 });

      await frame.locator('button[name="_savedok"]').click();

      const modal = page.locator('.modal');
      await expect(modal).toBeVisible({ timeout: 5000 });
      await expect(modal.locator('.modal-body')).toContainText('/restricted-section/bestaetigter-titel');

      // No redirect question follows for a placeholder path.
      await modal.getByRole('button', { name: 'Save and lock URL path', exact: true }).click();
      await waitForSaveSuccess(page, frame);

      await expect(frame.locator('input.sluggi-hidden-field')).toHaveValue('/restricted-section/bestaetigter-titel');
      await expect(frame.locator('sluggi-element')).not.toHaveAttribute('slug-pending', '');
      await expect(frame.locator('sluggi-element').locator('.sluggi-note')).toContainText('locked');
    });
  });

  test('copies the page URL from a synced page without sync access', async ({ page, context }) => {
    await context.grantPermissions(['clipboard-write']);

    await page.goto('/typo3/record/edit?edit[pages][37]=edit');
    const frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);
    const slugElement = frame.locator('sluggi-element');

    await slugElement.locator('.sluggi-copy-url-btn').click();

    await expect(slugElement.locator('.sluggi-copy-confirmation')).toBeVisible();
    await expect(slugElement.locator('.sluggi-copy-confirmation a')).toHaveAttribute(
      'href',
      /\/restricted-section\/synced-no-toggle$/
    );
  });
});
