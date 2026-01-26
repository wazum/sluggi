import {expect, test} from '@playwright/test';
import { expandPageTreeNode, getPageTreeNode, getPageTreeNodeLabel, getPageTreeEditInput, waitForPageTree } from '../fixtures/typo3-compat';

test.describe('Page Tree Inline Editing with Sync', () => {
  test('slug updates when title is changed via page tree inline editing', async ({ page }) => {
    await page.goto('/typo3/module/web/layout');
    await waitForPageTree(page, 10000);

    // Expand root node to show child pages
    await expandPageTreeNode(page, 1);

    const treeNode = await getPageTreeNode(page, 2);
    await expect(treeNode).toBeVisible({ timeout: 10000 });

    const titleElement = await getPageTreeNodeLabel(page, 2);
    await titleElement.dblclick();

    const timestamp = Date.now();
    const newTitle = `Edited ${timestamp}`;

    const treeInput = await getPageTreeEditInput(page);
    await expect(treeInput).toBeVisible({ timeout: 5000 });

    await treeInput.fill(newTitle);
    await treeInput.press('Enter');

    await expect(treeInput).not.toBeVisible({ timeout: 10000 });
    const updatedLabel = await getPageTreeNodeLabel(page, 2);
    await expect(updatedLabel).toContainText(newTitle, { timeout: 15000 });

    await page.goto('/typo3/record/edit?edit[pages][2]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const hiddenField = frame.locator('.sluggi-hidden-field');
    const expectedSlugPart = `edited-${timestamp}`;
    await expect(hiddenField).toHaveValue(new RegExp(expectedSlugPart));
  });
});
