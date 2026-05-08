import { test, expect, FrameLocator, Locator } from '@playwright/test';
import { expandPageTreeNode, getPageTreeNode, openNewSubpageForm, waitForEditForm, waitForNewPageForm, waitForPageTree } from '../fixtures/typo3-compat';

test.describe('Hierarchy Permission - Editor Slug Restrictions', () => {
  let frame: FrameLocator;
  let slugElement: Locator;

  test.use({
    extraHTTPHeaders: {
      'X-Playwright-Test-ID': 'hierarchy-permission',
    },
  });

  test.beforeEach(async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][28]=edit');
    frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);
    slugElement = frame.locator('sluggi-element');
  });

  test('shows locked prefix and editable segments based on hierarchy permissions', async () => {
    await expect(slugElement).toBeVisible();

    const prefix = slugElement.locator('.sluggi-prefix');
    await expect(prefix).toBeVisible();
    await expect(prefix).toContainText('/organization/department');

    const editableArea = slugElement.locator('.sluggi-editable');
    await expect(editableArea).toBeVisible();
    const editableText = await editableArea.textContent();
    expect(editableText).toContain('/institute/about-us');
  });

  test('editor can change segments within permission hierarchy and save', async ({ page }) => {
    const editableArea = slugElement.locator('.sluggi-editable');
    await editableArea.click();

    const input = slugElement.locator('input.sluggi-input');
    await input.fill('institute/about-page');
    await input.press('Enter');

    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    const hiddenField = frame.locator('.sluggi-hidden-field');
    await expect(hiddenField).toHaveValue('/organization/department/institute/about-page');

    await frame.locator('button[name="_savedok"]').click();
    await page.waitForURL(/edit/, { timeout: 10000 });

    const editFrame = page.frameLocator('iframe');
    await waitForEditForm(editFrame, page);

    const savedHiddenField = editFrame.locator('.sluggi-hidden-field');
    await expect(savedHiddenField).toHaveValue('/organization/department/institute/about-page');
  });

  test('new page has correct locked-prefix based on hierarchy permissions', async ({ page }) => {
    const editFrame = await openNewSubpageForm(page, 27);

    const slugElement = editFrame.locator('sluggi-element');
    await expect(slugElement).toBeVisible();
    await expect(slugElement).toHaveAttribute('locked-prefix', '/organization/department');

    const prefix = slugElement.locator('.sluggi-prefix');
    await expect(prefix).toBeVisible();
    await expect(prefix).toContainText('/organization/department');

    const editableArea = slugElement.locator('.sluggi-editable');
    await expect(editableArea).toBeVisible();
    const editableText = await editableArea.textContent();
    expect(editableText).toContain('/institute');
  });

  test('backend blocks attempt to modify locked prefix segments', async ({ page }) => {
    const hiddenField = frame.locator('.sluggi-hidden-field');
    await hiddenField.evaluate((el: HTMLInputElement) => {
      el.value = '/organization/other-department/institute/about-us';
    });

    await frame.locator('button[name="_savedok"]').click();
    await page.waitForURL(/edit/, { timeout: 10000 });

    const editFrame = page.frameLocator('iframe');
    await waitForEditForm(editFrame, page);

    const flashMessage = editFrame.locator('.alert-danger').first();
    await expect(flashMessage).toBeVisible({ timeout: 5000 });
  });

  test('editor with field permissions sees both sync and lock toggles', async () => {
    await expect(slugElement.locator('.sluggi-sync-toggle')).toBeVisible();
    await expect(slugElement.locator('.sluggi-lock-toggle')).toBeVisible();
  });

  test('cannot regenerate or enable sync when stored slug diverges (sync-on)', async ({ page }) => {
    const pageId = 64;
    const originalSlug = '/organization/department/diverged-sync-on';

    const proposalRequests: string[] = [];
    await page.route('**/record_slug_suggest**', (route) => {
      proposalRequests.push(route.request().url());
      route.continue();
    });

    await page.goto(`/typo3/record/edit?edit[pages][${pageId}]=edit`);
    const testFrame = page.frameLocator('iframe');
    await waitForEditForm(testFrame, page);
    const el = testFrame.locator('sluggi-element');

    const regenerate = el.locator('.sluggi-regenerate-btn');
    await expect(regenerate).toHaveAttribute('aria-disabled', 'true');

    const confirm = testFrame.locator('.sluggi-source-confirm').first();
    await expect(confirm).toHaveAttribute('disabled', '');
    await expect(confirm).toHaveAttribute('aria-disabled', 'true');
    await expect(confirm).toHaveAttribute('title', "Auto-sync is on but won't update this URL — turn it off to make this explicit.");

    // Button is disabled — dispatch a synthetic click and verify no AJAX proposal is sent.
    const baseline = proposalRequests.length;
    await confirm.dispatchEvent('click');
    await page.waitForTimeout(200);
    expect(proposalRequests.length).toBe(baseline);

    await expect(el.locator('.sluggi-sync-toggle')).not.toHaveAttribute('aria-disabled', 'true');

    const titleInput = testFrame.locator('input[data-formengine-input-name*="[title]"]');
    await titleInput.fill('Renamed by editor');
    await testFrame.locator('button[name="_savedok"]').first().click();
    await waitForEditForm(testFrame, page);
    await expect(page.locator('.callout-danger, .alert-danger')).toHaveCount(0);

    await expect(titleInput).toHaveValue('Renamed by editor');

    const hiddenSlug = testFrame.locator('input.sluggi-hidden-field').first();
    await expect(hiddenSlug).toHaveValue(originalSlug);
  });

  test('lock toggle works when stored slug diverges (sync-off)', async ({ page }) => {
    const pageId = 65;

    await page.goto(`/typo3/record/edit?edit[pages][${pageId}]=edit`);
    const testFrame = page.frameLocator('iframe');
    await waitForEditForm(testFrame, page);
    const el = testFrame.locator('sluggi-element');

    await expect(el.locator('.sluggi-regenerate-btn')).toHaveAttribute('aria-disabled', 'true');

    await expect(el.locator('.sluggi-sync-toggle')).toHaveAttribute('aria-disabled', 'true');

    // Inline advice shows the lock label before locking.
    const note = el.locator('.sluggi-note');
    await expect(note).toContainText("Lock the URL so future edits don't change it.");

    const lockToggle = el.locator('.sluggi-lock-toggle');
    await expect(lockToggle).not.toHaveAttribute('aria-disabled', 'true');
    await lockToggle.click();

    // Save. Lock persists.
    await testFrame.locator('button[name="_savedok"]').first().click();
    await waitForEditForm(testFrame, page);
    await expect(el.locator('.sluggi-lock-toggle')).toHaveClass(/is-locked/);
  });
});
