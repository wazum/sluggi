import { test, expect, FrameLocator, Locator } from '@playwright/test';

const NESTED_PAGE_ID = 19;

test.describe('Last Segment Only - Editor Restrictions', () => {
  let frame: FrameLocator;
  let slugElement: Locator;

  test.beforeEach(async ({ page }) => {
    await page.goto(`/typo3/record/edit?edit[pages][${NESTED_PAGE_ID}]=edit`);
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    slugElement = frame.locator('sluggi-element');
  });

  test('sluggi-element has last-segment-only attribute for non-admin user', async () => {
    await expect(slugElement).toBeVisible();
    await expect(slugElement).toHaveAttribute('last-segment-only', '');
  });

  test('prefix is shown as read-only and only last segment is editable', async () => {
    const prefix = slugElement.locator('.sluggi-prefix');
    const editableArea = slugElement.locator('.sluggi-editable');

    await expect(prefix).toBeVisible();
    await expect(prefix).toContainText('/parent-section');
    await expect(editableArea).toBeVisible();
  });

  test('editor can change only the last segment', async ({ page }) => {
    const editableArea = slugElement.locator('.sluggi-editable');
    await editableArea.click();

    const input = slugElement.locator('input.sluggi-input');
    await input.fill('new-segment');
    await input.press('Enter');

    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    const hiddenField = frame.locator('.sluggi-hidden-field');
    await expect(hiddenField).toHaveValue('/parent-section/new-segment');

    await frame.locator('button[name="_savedok"]').click();
    await page.waitForURL(/edit/, { timeout: 10000 });

    const editFrame = page.frameLocator('iframe');
    await expect(editFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const savedHiddenField = editFrame.locator('.sluggi-hidden-field');
    await expect(savedHiddenField).toHaveValue('/parent-section/new-segment');
  });

  test('slashes in input are stripped for last-segment-only mode', async () => {
    const editableArea = slugElement.locator('.sluggi-editable');
    await editableArea.click();

    const input = slugElement.locator('input.sluggi-input');
    await input.fill('segment/with/slashes');
    await input.press('Enter');

    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    const hiddenField = frame.locator('.sluggi-hidden-field');
    const value = await hiddenField.inputValue();
    expect(value).not.toContain('/segment/with/slashes');
  });

  test('clearing the last segment reverts to previous value on blur', async ({ page }) => {
    const editableArea = slugElement.locator('.sluggi-editable');
    const originalValue = await frame.locator('.sluggi-hidden-field').inputValue();

    await editableArea.click();
    const input = slugElement.locator('input.sluggi-input');
    await input.fill('');
    await input.press('Tab');

    // Should revert to original value
    const hiddenField = frame.locator('.sluggi-hidden-field');
    await expect(hiddenField).toHaveValue(originalValue);
  });

  test('backend blocks attempt to change parent segment', async ({ page }) => {
    const hiddenField = frame.locator('.sluggi-hidden-field');
    await hiddenField.evaluate((el: HTMLInputElement) => {
      el.value = '/different-parent/nested-page';
    });

    await frame.locator('button[name="_savedok"]').click();
    await page.waitForURL(/edit/, { timeout: 10000 });

    const editFrame = page.frameLocator('iframe');
    await expect(editFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const flashMessage = editFrame.locator('.alert-danger').first();
    await expect(flashMessage).toBeVisible({ timeout: 5000 });
  });

  test('slash in title via page tree inline edit does not create extra segment', async ({ page }) => {
    const SYNCED_PAGE_ID = 20;

    // Navigate to page module to access page tree
    await page.goto('/typo3/module/web/layout');

    const pageTree = page.locator('.scaffold-content-navigation-component');
    await expect(pageTree).toBeVisible({ timeout: 10000 });

    // Expand tree nodes to find our test page
    const rootNode = pageTree.locator('[data-id="1"]');
    await expect(rootNode).toBeVisible({ timeout: 10000 });
    const rootToggle = rootNode.locator('.node-toggle');
    if (await rootToggle.isVisible()) {
      await rootToggle.click();
      await page.waitForTimeout(500);
    }

    // Expand parent section (uid=18)
    const parentNode = pageTree.locator('[data-id="18"]');
    if (await parentNode.isVisible({ timeout: 2000 }).catch(() => false)) {
      const parentToggle = parentNode.locator('.node-toggle');
      if (await parentToggle.isVisible()) {
        await parentToggle.click();
        await page.waitForTimeout(500);
      }
    }

    // Find and double-click our synced test page to edit inline
    const treeNode = pageTree.locator(`[data-id="${SYNCED_PAGE_ID}"]`);
    await expect(treeNode).toBeVisible({ timeout: 10000 });

    const titleElement = treeNode.locator('.node-contentlabel');
    await titleElement.dblclick();

    // Enter title with slash
    const treeInput = pageTree.locator('input.node-edit');
    await expect(treeInput).toBeVisible({ timeout: 5000 });
    await treeInput.fill('Test Page/With Slash');
    await treeInput.press('Enter');

    // Wait for save to complete
    await expect(treeInput).not.toBeVisible({ timeout: 10000 });

    // Verify the slug in the edit form
    await page.goto(`/typo3/record/edit?edit[pages][${SYNCED_PAGE_ID}]=edit`);
    const editFrame = page.frameLocator('iframe');
    await expect(editFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const hiddenField = editFrame.locator('.sluggi-hidden-field');
    const slugValue = await hiddenField.inputValue();

    // Slash should be replaced with hyphen, NOT create a new segment
    expect(slugValue).toContain('test-page-with-slash');
    expect(slugValue).not.toContain('/test-page/with-slash');
    expect(slugValue).not.toContain('/with-slash');
  });
});
