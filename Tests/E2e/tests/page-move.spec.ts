import { test, expect } from '@playwright/test';

const CHILD_PAGE_ID = 17;

test.describe('Page Move - Slug Update', () => {
  test('moving a page into another updates slug with parent prefix', async ({ page }) => {
    await page.goto('/typo3/module/web/layout');
    const pageTree = page.locator('.scaffold-content-navigation-component');
    await expect(pageTree).toBeVisible({ timeout: 15000 });

    const rootNode = pageTree.locator('[data-id="1"]');
    await expect(rootNode).toBeVisible({ timeout: 10000 });
    const rootToggle = rootNode.locator('.node-toggle');
    if (await rootToggle.isVisible()) {
      await rootToggle.click();
    }

    // Page 17 has slug /child-page, page 16 has slug /parent-page
    const childNode = page.getByRole('treeitem', { name: 'Child Page' });
    await expect(childNode).toBeVisible({ timeout: 10000 });
    await childNode.click({ button: 'right' });
    const cutMenuItem = page.getByRole('menuitem', { name: 'Cut' });
    await cutMenuItem.click();
    await expect(cutMenuItem).not.toBeVisible();

    const parentNode = page.getByRole('treeitem', { name: 'Parent Page' });
    await expect(parentNode).toBeVisible({ timeout: 10000 });
    await parentNode.click({ button: 'right' });
    await page.getByRole('menuitem', { name: 'Paste into' }).click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible({ timeout: 5000 });
    await dialog.getByRole('button', { name: 'OK', exact: true }).click();
    await expect(dialog).not.toBeVisible({ timeout: 5000 });

    await page.goto(`/typo3/record/edit?edit[pages][${CHILD_PAGE_ID}]=edit`);
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    // After move, child slug should be parent slug + child segment
    const hiddenField = frame.locator('.sluggi-hidden-field');
    const slug = await hiddenField.inputValue();
    expect(slug).toBe('/parent-page/child-page');
  });
});
