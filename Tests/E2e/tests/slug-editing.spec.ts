import { test, expect, Locator, FrameLocator } from '@playwright/test';

const TEST_PAGE_ID = 2;

test.describe('Slug Editing - TYPO3 Integration', () => {
  let frame: FrameLocator;
  let slugElement: Locator;

  test.beforeEach(async ({ page }) => {
    await page.goto(`/typo3/record/edit?edit[pages][${TEST_PAGE_ID}]=edit`);
    frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    slugElement = frame.locator('sluggi-element');
  });

  test('sluggi-element renders in TYPO3 page form with correct attributes', async () => {
    await expect(slugElement).toBeVisible();
    await expect(slugElement).toHaveAttribute('table-name', 'pages');
    await expect(slugElement).toHaveAttribute('field-name', 'slug');
    await expect(slugElement).toHaveAttribute('record-id', String(TEST_PAGE_ID));
  });

  test('regenerate button triggers AJAX proposal from TYPO3', async ({ page }) => {
    // Use dedicated page 8 with sync OFF
    await page.goto('/typo3/record/edit?edit[pages][8]=edit');
    const regenFrame = page.frameLocator('iframe');
    await expect(regenFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const regenSlugElement = regenFrame.locator('sluggi-element');

    const regenerateButton = regenSlugElement.locator('.sluggi-regenerate-btn');
    await expect(regenerateButton).toBeVisible();

    await regenerateButton.click();

    // Wait for spinner to appear then disappear (indicates AJAX completed)
    await regenSlugElement.locator('.sluggi-spinner').waitFor({ state: 'visible', timeout: 2000 }).catch(() => {});
    await regenSlugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 });

    // The regenerated slug should match the page title "Regenerate Test" -> "/regenerate-test"
    const hiddenField = regenFrame.locator('.sluggi-hidden-field');
    await expect(hiddenField).toHaveValue('/regenerate-test');
  });

  test('form save persists slug to database', async ({ page }) => {
    // Use dedicated page 6 for this test (sync is OFF by default in fixture)
    await page.goto('/typo3/record/edit?edit[pages][6]=edit');
    let editFrame = page.frameLocator('iframe');
    await expect(editFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
    const editSlugElement = editFrame.locator('sluggi-element');

    const editableArea = editSlugElement.locator('.sluggi-editable');
    await editableArea.click();

    const input = editSlugElement.locator('input.sluggi-input');
    await input.fill('save-test-edited');
    await input.press('Enter');

    await editSlugElement.locator('.sluggi-spinner').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});

    await editFrame.locator('button[name="_savedok"]').click();
    await page.waitForURL(/edit/, { timeout: 10000 });

    editFrame = page.frameLocator('iframe');
    await expect(editFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const hiddenField = editFrame.locator('.sluggi-hidden-field');
    await expect(hiddenField).toHaveValue('/save-test-edited');
  });

  test('slash in title is replaced with hyphen on regenerate', async ({ page }) => {
    // Use page 8 for regenerate test
    await page.goto('/typo3/record/edit?edit[pages][8]=edit');
    const editFrame = page.frameLocator('iframe');
    await expect(editFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    // Change the title to include slashes
    const titleInput = editFrame.locator('input[data-formengine-input-name*="[title]"]');
    await titleInput.clear();
    await titleInput.fill('Test/With/Slashes');
    await titleInput.blur();

    // Click regenerate button
    const slugElement = editFrame.locator('sluggi-element');
    const regenerateButton = slugElement.locator('.sluggi-regenerate-btn');
    await regenerateButton.click();

    // Slug should have slashes replaced with hyphens, NOT create extra segments
    const hiddenField = editFrame.locator('.sluggi-hidden-field');
    await expect(hiddenField).toHaveValue('/test-with-slashes', { timeout: 10000 });
  });
});
