import { expect, FrameLocator, test } from '@playwright/test';
import { getMultiEditUrl, waitForEditForm } from '../fixtures/typo3-compat';

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
    await waitForEditForm(frame, page);

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
    await waitForEditForm(frame, page);

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

  test('Revert update reverts ALL changed slugs, not just the last one', async ({ page }) => {
    const multiEditUrl = await getMultiEditUrl(page, 'pages', [46, 47, 48], ['slug']);
    await page.goto(multiEditUrl);
    frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);

    // Dismiss any stale notifications from prior tests
    for (const btn of await page.locator('.alert-dismissible .close').all()) {
      await btn.click().catch(() => {});
    }

    const slugElements = frame.locator('sluggi-element');
    await expect(slugElements).toHaveCount(3, { timeout: 10000 });

    const originals: string[] = [];
    for (let i = 0; i < 3; i++) {
      originals.push(await frame.locator('input.sluggi-hidden-field').nth(i).inputValue());
    }

    const timestamp = Date.now();
    for (let i = 0; i < 3; i++) {
      const el = slugElements.nth(i);
      await el.locator('.sluggi-editable').click();
      const input = el.locator('input.sluggi-input');
      await input.fill(`multi-revert-${timestamp}-${i + 1}`);
      await input.press('Enter');
      await el.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});
    }

    await frame.locator('button[name="_savedok"]').click();

    const modal = page.locator('.modal');
    await expect(modal).toBeVisible({ timeout: 5000 });
    await modal.getByRole('button', { name: 'Create Redirects', exact: true }).click();
    await modal.waitFor({ state: 'hidden', timeout: 5000 });

    const notification = page.locator('.alert-info:has(a:has-text("Revert"))');
    await expect(notification).toBeVisible({ timeout: 10000 });

    // Bug check: there must be exactly ONE notification (not three, not one-per-page)
    expect(await page.locator('.alert-info:has(a:has-text("Revert"))').count()).toBe(1);

    const revertButton = notification.locator('a:has-text("Revert")').first();
    await Promise.all([
      page.waitForResponse(
        resp => resp.url().includes('revert') || resp.url().includes('correlation'),
        { timeout: 10000 },
      ),
      revertButton.click(),
    ]);
    await page.waitForLoadState('load');

    // Re-open multi-edit and assert ALL three slugs are restored
    await page.goto(multiEditUrl);
    const reloadedFrame = page.frameLocator('iframe');
    await waitForEditForm(reloadedFrame, page);

    for (let i = 0; i < 3; i++) {
      await expect(reloadedFrame.locator('input.sluggi-hidden-field').nth(i))
        .toHaveValue(originals[i], { timeout: 10000 });
    }
  });

  test('no modal appears when no slugs are changed', async ({ page }) => {
    const multiEditUrl = await getMultiEditUrl(page, 'pages', [46, 47, 48], ['slug']);
    await page.goto(multiEditUrl);
    frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);

    const slugElements = frame.locator('sluggi-element');
    await expect(slugElements).toHaveCount(3, { timeout: 10000 });

    await frame.locator('button[name="_savedok"]').click();

    const modal = page.locator('.modal');
    await expect(modal).not.toBeVisible({ timeout: 2000 });

    await page.waitForURL(/edit/, { timeout: 10000 });
  });
});
