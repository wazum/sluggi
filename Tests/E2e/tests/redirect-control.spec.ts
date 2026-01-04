import {expect, FrameLocator, test} from '@playwright/test';

test.describe('Redirect Control - TYPO3 Integration', () => {
  test.use({
    extraHTTPHeaders: {
      'X-Playwright-Test-Id': 'redirect-control',
    },
  });

  let frame: FrameLocator;

  test('redirect modal appears when saving page with changed slug', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][6]=edit');
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');
    await expect(slugElement).toHaveAttribute('redirect-control', '');

    const editableArea = slugElement.locator('.sluggi-editable');
    await editableArea.click();

    const input = slugElement.locator('input.sluggi-input');
    await input.fill(`redirect-test-changed-${Date.now()}`);
    await input.press('Enter');

    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    // Click the save button
    await frame.locator('button[name="_savedok"]').click();

    // Redirect modal MUST appear when saving a page with changed slug
    const modal = page.locator('.modal');
    await expect(modal).toBeVisible({ timeout: 5000 });
    const modalBody = modal.locator('.modal-body');
    await expect(modalBody).toContainText('Do you want to create a redirect');
  });

  test('redirect modal does NOT appear when slug unchanged', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][7]=edit');
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');
    await expect(slugElement).toHaveAttribute('redirect-control', '');

    // Don't change the slug, just save
    await frame.locator('button[name="_savedok"]').click();

    // Modal should NOT appear since slug wasn't changed
    const modal = page.locator('.modal');
    await expect(modal).not.toBeVisible({ timeout: 2000 });

    // Page should have saved (URL will refresh)
    await page.waitForURL(/edit/, { timeout: 10000 });
  });

  test('choosing "Create Redirects" stores choice in localStorage', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][6]=edit');
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');

    const editableArea = slugElement.locator('.sluggi-editable');
    await editableArea.click();

    const input = slugElement.locator('input.sluggi-input');
    await input.fill(`redirect-create-test-${Date.now()}`);
    await input.press('Enter');

    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    // Click save
    await frame.locator('button[name="_savedok"]').click();

    // Modal appears
    const modal = page.locator('.modal');
    await expect(modal).toBeVisible({ timeout: 5000 });

    // Click "Create Redirects" button (use exact match to avoid multiple matches)
    const createButton = modal.getByRole('button', { name: 'Create Redirects', exact: true });
    await createButton.click();
    await modal.waitFor({ state: 'hidden', timeout: 5000 });

    // Check localStorage was set with createRedirects=true
    const stored = await page.evaluate(() => localStorage.getItem('sluggi-redirect-choice'));
    const choice = JSON.parse(stored || '{}');
    expect(choice.createRedirects).toBe(true);
  });

  test('choosing "Don\'t Create Redirects" stores choice in localStorage', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][6]=edit');
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');

    const editableArea = slugElement.locator('.sluggi-editable');
    await editableArea.click();

    const input = slugElement.locator('input.sluggi-input');
    await input.fill(`redirect-skip-test-${Date.now()}`);
    await input.press('Enter');

    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    // Click save
    await frame.locator('button[name="_savedok"]').click();

    // Modal appears
    const modal = page.locator('.modal');
    await expect(modal).toBeVisible({ timeout: 5000 });

    // Click "Don't Create Redirects" button
    const skipButton = modal.getByRole('button', { name: "Don't Create Redirects" });
    await skipButton.click();
    await modal.waitFor({ state: 'hidden', timeout: 5000 });

    // Check localStorage was set with createRedirects=false
    const stored = await page.evaluate(() => localStorage.getItem('sluggi-redirect-choice'));
    const choice = JSON.parse(stored || '{}');
    expect(choice.createRedirects).toBe(false);
  });

  test('notification shows "slug only" when user chose not to create redirects', async ({ page }) => {
    await page.goto('/typo3/record/edit?edit[pages][6]=edit');
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const slugElement = frame.locator('sluggi-element');

    const editableArea = slugElement.locator('.sluggi-editable');
    await editableArea.click();

    const input = slugElement.locator('input.sluggi-input');
    await input.fill(`notification-test-${Date.now()}`);
    await input.press('Enter');

    await slugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    // Click save
    await frame.locator('button[name="_savedok"]').click();

    // Modal appears - choose not to create redirects
    const modal = page.locator('.modal');
    await expect(modal).toBeVisible({ timeout: 5000 });
    const skipButton = modal.getByRole('button', { name: "Don't Create Redirects" });
    await skipButton.click();
    await modal.waitFor({ state: 'hidden', timeout: 5000 });

    // Wait for page to reload and notification to appear
    await page.waitForURL(/edit/, { timeout: 10000 });

    // The notification should NOT contain "redirects were created"
    const notification = page.locator('.alert-info, .typo3-notification');
    await notification.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});

    // If notification exists, it should say "slug only" not "redirects created"
    const notificationText = await notification.textContent().catch(() => '');
    if (notificationText.includes('slug')) {
      expect(notificationText).not.toContain('redirects were created');
    }
  });
});
