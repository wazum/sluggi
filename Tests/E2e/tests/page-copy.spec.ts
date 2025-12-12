import { test, expect } from '@playwright/test';

test.describe('Page Copy - Slug Update', () => {
  test('copying a page into another updates slug with parent prefix', async ({ page }) => {
    await page.goto('/typo3/module/web/layout');
    const pageTree = page.locator('.scaffold-content-navigation-component');
    await expect(pageTree).toBeVisible({ timeout: 15000 });

    const rootNode = pageTree.locator('[data-id="1"]');
    await expect(rootNode).toBeVisible({ timeout: 10000 });
    const rootToggle = rootNode.locator('.node-toggle');
    if (await rootToggle.isVisible()) {
      await rootToggle.click();
    }

    // Copy "Copy Source" (page 21) into "Copy Target" (page 22)
    const sourceNode = page.getByRole('treeitem', { name: 'Copy Source' });
    await expect(sourceNode).toBeVisible({ timeout: 10000 });
    await sourceNode.click({ button: 'right' });
    const copyMenuItem = page.getByRole('menuitem', { name: 'Copy' });
    await copyMenuItem.click();
    await expect(copyMenuItem).not.toBeVisible();

    const targetNode = page.getByRole('treeitem', { name: 'Copy Target' });
    await expect(targetNode).toBeVisible({ timeout: 10000 });
    await targetNode.click({ button: 'right' });
    const pasteMenuItem = page.getByRole('menuitem', { name: 'Paste into' });
    await expect(pasteMenuItem).toBeVisible({ timeout: 5000 });
    await pasteMenuItem.click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible({ timeout: 5000 });
    await dialog.getByRole('button', { name: 'OK', exact: true }).click();
    await expect(dialog).not.toBeVisible({ timeout: 5000 });

    // Reload tree to show the new page
    await page.getByRole('button', { name: 'Open page tree options menu' }).click();
    await page.getByRole('button', { name: 'Reload the tree from server' }).click();

    // Expand Copy Target to see the copied page
    const targetToggle = page.getByRole('treeitem', { name: 'Copy Target' }).locator('.node-toggle');
    await expect(targetToggle).toBeVisible({ timeout: 10000 });
    await targetToggle.click();

    // Click the copied page (second "Copy Source" in tree, now under Copy Target)
    const copiedNode = page.getByRole('treeitem', { name: 'Copy Source' }).nth(1);
    await expect(copiedNode).toBeVisible({ timeout: 10000 });
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
    expect(slug).toBe('/copy-target/copy-source');
  });
});
