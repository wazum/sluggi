import { test, expect } from '@playwright/test';
import { expandPageTreeNode, getPageTreeItemByName, reloadPageTree } from '../fixtures/typo3-compat';

test.describe('Page Copy - Slug Update', () => {
  test('copying a page into another updates slug with parent prefix', async ({ page }) => {
    await page.goto('/typo3/module/web/layout');
    const pageTree = page.locator('.scaffold-content-navigation-component');
    await expect(pageTree).toBeVisible({ timeout: 15000 });

    // Expand root node to show child pages
    await expandPageTreeNode(page, 1);

    // Copy "Copy Source" (page 23) into "Copy Target" (page 24)
    const sourceNode = await getPageTreeItemByName(page, /Copy Source/);
    await expect(sourceNode.first()).toBeVisible({ timeout: 10000 });
    await sourceNode.first().click({ button: 'right' });
    const copyMenuItem = page.getByRole('menuitem', { name: 'Copy' });
    await copyMenuItem.click();
    await expect(copyMenuItem).not.toBeVisible();

    const targetNode = await getPageTreeItemByName(page, /Copy Target/);
    await expect(targetNode.first()).toBeVisible({ timeout: 10000 });
    await targetNode.first().click({ button: 'right' });
    const pasteMenuItem = page.getByRole('menuitem', { name: 'Paste into' });
    await expect(pasteMenuItem).toBeVisible({ timeout: 5000 });
    await pasteMenuItem.click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible({ timeout: 5000 });
    await dialog.getByRole('button', { name: 'OK', exact: true }).click();
    await expect(dialog).not.toBeVisible({ timeout: 5000 });

    // Reload tree to show the new page
    await reloadPageTree(page);

    // Wait for tree reload by checking the target node is visible again
    const targetNodeAfterReload = await getPageTreeItemByName(page, /Copy Target/);
    await expect(targetNodeAfterReload.first()).toBeVisible({ timeout: 15000 });

    // Expand Copy Target to see the copied page
    await expandPageTreeNode(page, 24);

    // Click the copied page (second "Copy Source" in tree, now under Copy Target)
    const copiedNodes = await getPageTreeItemByName(page, /Copy Source/);
    const copiedNode = copiedNodes.nth(1);
    await expect(copiedNode).toBeVisible({ timeout: 20000 });
    await copiedNode.click();

    // Get the page ID from URL
    await page.waitForURL(/module\/web\/layout.*id=\d+/);
    const copiedPageId = page.url().match(/id=(\d+)/)?.[1];
    expect(copiedPageId).toBeTruthy();

    await page.goto(`/typo3/record/edit?edit[pages][${copiedPageId}]=edit`);
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const hiddenField = frame.locator('.sluggi-hidden-field');
    const slug = await hiddenField.inputValue();
    // Allow suffix when copy creates duplicate (retries within same run)
    expect(slug).toMatch(/^\/copy-target\/copy-source(-\d+)?$/);
  });
});
