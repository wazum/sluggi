import { expect, FrameLocator, test } from '@playwright/test';
import { getMultiEditUrl } from '../fixtures/typo3-compat';

test.describe('Redirect Control - Multi-Edit', () => {
  test.use({
    extraHTTPHeaders: {
      'X-Playwright-Test-Id': 'redirect-control',
    },
  });

  let frame: FrameLocator;

  test('shows only ONE modal when multiple slugs are changed', async ({ page }) => {
    const multiEditUrl = await getMultiEditUrl(page, 'pages', [46, 47, 48], ['slug']);
    await page.goto(multiEditUrl);
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1').first()).toContainText('Edit Page', { timeout: 15000 });

    const slugElements = frame.locator('sluggi-element');
    await expect(slugElements).toHaveCount(3, { timeout: 10000 });

    // Use timestamp to ensure unique slugs on each test run
    const timestamp = Date.now();
    for (let i = 0; i < 3; i++) {
      const slugElement = slugElements.nth(i);
      await expect(slugElement).toHaveAttribute('redirect-control', '');

      const editableArea = slugElement.locator('.sluggi-editable');
      await editableArea.click();

      const input = slugElement.locator('input.sluggi-input');
      await input.fill(`multi-edit-changed-${timestamp}-${i + 1}`);
      await input.press('Enter');

      await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});
    }

    await frame.locator('button[name="_savedok"]').click();

    const modal = page.locator('.modal');
    await expect(modal).toBeVisible({ timeout: 5000 });
    const modalBody = modal.locator('.modal-body');
    await expect(modalBody).toContainText('Do you want to create a redirect');

    let modalCount = 0;
    const modals = page.locator('.modal:visible');
    modalCount = await modals.count();
    expect(modalCount).toBe(1);
  });

  test('choice applies to all changed slugs when choosing "Create Redirects"', async ({ page }) => {
    const multiEditUrl = await getMultiEditUrl(page, 'pages', [46, 47, 48], ['slug']);
    await page.goto(multiEditUrl);
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1').first()).toContainText('Edit Page', { timeout: 15000 });

    const slugElements = frame.locator('sluggi-element');
    await expect(slugElements).toHaveCount(3, { timeout: 10000 });

    // Use timestamp to ensure unique slugs on each test run
    const timestamp = Date.now();
    for (let i = 0; i < 3; i++) {
      const slugElement = slugElements.nth(i);
      const editableArea = slugElement.locator('.sluggi-editable');
      await editableArea.click();

      const input = slugElement.locator('input.sluggi-input');
      await input.fill(`multi-create-redirect-${timestamp}-${i + 1}`);
      await input.press('Enter');

      await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});
    }

    await frame.locator('button[name="_savedok"]').click();

    const modal = page.locator('.modal');
    await expect(modal).toBeVisible({ timeout: 5000 });

    const createButton = modal.getByRole('button', { name: 'Create Redirects', exact: true });
    await createButton.click();
    await modal.waitFor({ state: 'hidden', timeout: 5000 });

    const stored = await page.evaluate(() => localStorage.getItem('sluggi-redirect-choice'));
    const choice = JSON.parse(stored || '{}');
    expect(choice.createRedirects).toBe(true);
  });

  test('no modal appears when no slugs are changed', async ({ page }) => {
    const multiEditUrl = await getMultiEditUrl(page, 'pages', [46, 47, 48], ['slug']);
    await page.goto(multiEditUrl);
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1').first()).toContainText('Edit Page', { timeout: 15000 });

    const slugElements = frame.locator('sluggi-element');
    await expect(slugElements).toHaveCount(3, { timeout: 10000 });

    await frame.locator('button[name="_savedok"]').click();

    const modal = page.locator('.modal');
    await expect(modal).not.toBeVisible({ timeout: 2000 });

    await page.waitForURL(/edit/, { timeout: 10000 });
  });
});
