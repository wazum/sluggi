import {expect, test} from '@playwright/test';

test.describe('Page Tree Inline Editing with Sync', () => {
  test('slug updates when title is changed via page tree inline editing', async ({ page }) => {
    await page.goto('/typo3/module/web/layout');

    const pageTree = page.locator('.scaffold-content-navigation-component');
    await expect(pageTree).toBeVisible({ timeout: 10000 });

    const rootNode = pageTree.locator('[data-id="1"]');
    await expect(rootNode).toBeVisible({ timeout: 10000 });
    const rootToggle = rootNode.locator('.node-toggle');
    if (await rootToggle.isVisible()) {
      await rootToggle.click();
    }

    const treeNode = pageTree.locator('[data-id="2"]');
    await expect(treeNode).toBeVisible({ timeout: 10000 });

    const titleElement = treeNode.locator('.node-contentlabel');
    await titleElement.dblclick();

    const timestamp = Date.now();
    const newTitle = `Edited ${timestamp}`;

    const treeInput = pageTree.locator('input.node-edit');
    await expect(treeInput).toBeVisible({ timeout: 5000 });

    await treeInput.fill(newTitle);
    await treeInput.press('Enter');

    await expect(treeInput).not.toBeVisible({ timeout: 10000 });
    await expect(treeNode.locator('.node-contentlabel')).toContainText(newTitle, { timeout: 15000 });

    await page.goto('/typo3/record/edit?edit[pages][2]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const hiddenField = frame.locator('.sluggi-hidden-field');
    const expectedSlugPart = `edited-${timestamp}`;
    await expect(hiddenField).toHaveValue(new RegExp(expectedSlugPart));
  });
});
