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
});
