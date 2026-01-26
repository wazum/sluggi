import { test, expect, FrameLocator, Locator } from '@playwright/test';
import { expandPageTreeNode, getPageTreeNode, getPageTreeNodeLabel, getPageTreeEditInput, waitForPageTree } from '../fixtures/typo3-compat';

test.describe('Last Segment Only - Editor Restrictions', () => {
  let frame: FrameLocator;
  let slugElement: Locator;

  test.use({
    extraHTTPHeaders: {
      'X-Playwright-Test-ID': 'last-segment-only',
    },
  });

  test.beforeEach(async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][19]=edit');
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
    await page.goto('/typo3/record/edit?edit[pages][20]=edit');
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    slugElement = frame.locator('sluggi-element');

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

    const hiddenField = frame.locator('.sluggi-hidden-field');
    await expect(hiddenField).toHaveValue(originalValue);
  });

  test('backend blocks attempt to change parent segment', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][21]=edit');
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

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

  test('new page via context menu shows parent prefix as locked (issue #128)', async ({ page }) => {
    await page.goto('/typo3/module/web/layout');
    await waitForPageTree(page, 10000);

    // Editor has mount point at page 18, so it's directly visible
    const parentNode = await getPageTreeNode(page, 18);
    await expect(parentNode).toBeVisible({ timeout: 10000 });
    await parentNode.click({ button: 'right' });

    const newSubpageMenuItem = page.getByRole('menuitem', { name: 'New subpage' });
    await expect(newSubpageMenuItem).toBeVisible({ timeout: 5000 });
    await newSubpageMenuItem.click();

    const editFrame = page.frameLocator('iframe');
    await expect(editFrame.locator('h1')).toContainText('Create new Page', { timeout: 15000 });

    const slugElement = editFrame.locator('sluggi-element');
    await expect(slugElement).toBeVisible();
    await expect(slugElement).toHaveAttribute('last-segment-only', '');
    await expect(slugElement).toHaveAttribute('locked-prefix', '/parent-section');

    const titleInput = editFrame.locator('input[data-formengine-input-name*="[title]"]');
    await titleInput.fill('New Test Subpage');
    await titleInput.press('Tab');

    await page.waitForTimeout(1000);

    const prefix = slugElement.locator('.sluggi-prefix');
    await expect(prefix).toBeVisible();
    await expect(prefix).toContainText('/parent-section');

    const hiddenField = editFrame.locator('.sluggi-hidden-field');
    const slugValue = await hiddenField.inputValue();
    expect(slugValue).toContain('/parent-section/');
  });

  test('slash in title via page tree inline edit does not create extra segment', async ({ page }) => {
    await page.goto('/typo3/module/web/layout');
    await waitForPageTree(page, 10000);

    // Expand parent section node (editor has mount point at page 18, no access to root)
    await expandPageTreeNode(page, 18);

    const treeNode = await getPageTreeNode(page, 22);
    await expect(treeNode).toBeVisible({ timeout: 10000 });

    const titleElement = await getPageTreeNodeLabel(page, 22);
    await titleElement.dblclick();

    const treeInput = await getPageTreeEditInput(page);
    await expect(treeInput).toBeVisible({ timeout: 5000 });
    await treeInput.fill('Test Page/With Slash');
    await treeInput.press('Enter');

    await expect(treeInput).not.toBeVisible({ timeout: 10000 });

    await page.goto('/typo3/record/edit?edit[pages][22]=edit');
    const editFrame = page.frameLocator('iframe');
    await expect(editFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const hiddenField = editFrame.locator('.sluggi-hidden-field');
    const slugValue = await hiddenField.inputValue();

    expect(slugValue).toContain('test-page-with-slash');
    expect(slugValue).not.toContain('/test-page/with-slash');
    expect(slugValue).not.toContain('/with-slash');
  });
});
