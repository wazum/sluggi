import { test, expect, FrameLocator, Locator } from '@playwright/test';

const ABOUT_US_PAGE_ID = 26;

test.describe('Hierarchy Permission - Editor Slug Restrictions', () => {
  let frame: FrameLocator;
  let slugElement: Locator;

  test.use({
    extraHTTPHeaders: {
      'X-Playwright-Test-ID': 'hierarchy-permission',
    },
  });

  test.beforeEach(async ({ page }) => {
    await page.goto(`/typo3/record/edit?edit[pages][${ABOUT_US_PAGE_ID}]=edit`);
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
    // In hierarchy permission mode, new pages should have locked-prefix based on
    // the user's permissions, not the entire parent slug
    await page.goto('/typo3/module/web/layout');

    const pageTree = page.locator('.scaffold-content-navigation-component');
    await expect(pageTree).toBeVisible({ timeout: 10000 });

    // Right-click on Institute (page 25) to create a subpage
    const instituteNode = pageTree.locator('[data-id="25"]');
    await expect(instituteNode).toBeVisible({ timeout: 10000 });
    await instituteNode.click({ button: 'right' });

    const newSubpageMenuItem = page.getByRole('menuitem', { name: 'New subpage' });
    await expect(newSubpageMenuItem).toBeVisible({ timeout: 5000 });
    await newSubpageMenuItem.click();

    const editFrame = page.frameLocator('iframe');
    await expect(editFrame.locator('h1')).toContainText('Create new Page', { timeout: 15000 });

    const slugElement = editFrame.locator('sluggi-element');
    await expect(slugElement).toBeVisible();

    // Locked prefix should be /organization/department (above user's permission level)
    await expect(slugElement).toHaveAttribute('locked-prefix', '/organization/department');

    // The prefix display should show the locked part
    const prefix = slugElement.locator('.sluggi-prefix');
    await expect(prefix).toBeVisible();
    await expect(prefix).toContainText('/organization/department');

    // The editable area should show /institute (the part user can edit)
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
