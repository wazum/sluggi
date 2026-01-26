import { test, expect, FrameLocator, Locator } from '@playwright/test';
import { expandPageTreeNode, getPageTreeNode, waitForPageTree } from '../fixtures/typo3-compat';

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
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
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
    await expect(editFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const savedHiddenField = editFrame.locator('.sluggi-hidden-field');
    await expect(savedHiddenField).toHaveValue('/organization/department/institute/about-page');
  });

  test('new page has correct locked-prefix based on hierarchy permissions', async ({ page }) => {
    await page.goto('/typo3/module/web/layout');
    await waitForPageTree(page, 10000);

    // Editor has mount point at page 27, so it's directly visible
    const instituteNode = await getPageTreeNode(page, 27);
    await expect(instituteNode).toBeVisible({ timeout: 10000 });
    await instituteNode.click({ button: 'right' });

    const newSubpageMenuItem = page.getByRole('menuitem', { name: 'New subpage' });
    await expect(newSubpageMenuItem).toBeVisible({ timeout: 5000 });
    await newSubpageMenuItem.click();

    const editFrame = page.frameLocator('iframe');
    await expect(editFrame.locator('h1')).toContainText('Create new Page', { timeout: 15000 });

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
    await expect(editFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const flashMessage = editFrame.locator('.alert-danger').first();
    await expect(flashMessage).toBeVisible({ timeout: 5000 });
  });

  test('editor with field permissions sees both sync and lock toggles', async () => {
    await expect(slugElement.locator('.sluggi-sync-toggle')).toBeVisible();
    await expect(slugElement.locator('.sluggi-lock-toggle')).toBeVisible();
  });
});
