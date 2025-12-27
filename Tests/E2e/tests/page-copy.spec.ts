import { test, expect } from '@playwright/test';
import { expandPageTreeNode, getPageTreeItemByName, getListModuleUrl } from '../fixtures/typo3-compat';

test.describe('Page Copy - Slug Update', () => {
  test('copying a page into another updates slug with parent prefix', async ({ page }) => {
    await page.goto('/typo3/module/web/layout');
    const pageTree = page.locator('.scaffold-content-navigation-component');
    await expect(pageTree).toBeVisible({ timeout: 15000 });

    await expandPageTreeNode(page, 1);

    // Copy "Copy Source" (page 23)
    const sourceNode = await getPageTreeItemByName(page, /Copy Source/);
    await expect(sourceNode.first()).toBeVisible({ timeout: 10000 });
    await sourceNode.first().click({ button: 'right' });

    const copyMenuItem = page.getByRole('menuitem', { name: 'Copy' });
    await expect(copyMenuItem).toBeVisible({ timeout: 5000 });
    await copyMenuItem.click();
    await expect(copyMenuItem).not.toBeVisible({ timeout: 5000 });

    // Paste into "Copy Target" (page 24) - wait for paste AJAX to complete
    const targetNode = await getPageTreeItemByName(page, /Copy Target/);
    await expect(targetNode.first()).toBeVisible({ timeout: 10000 });
    await targetNode.first().click({ button: 'right' });

    const pasteMenuItem = page.getByRole('menuitem', { name: 'Paste into' });
    await expect(pasteMenuItem).toBeVisible({ timeout: 5000 });

    // Intercept the paste response to get the new page ID
    const pasteResponsePromise = page.waitForResponse(
      response => response.url().includes('ajax') && response.status() === 200,
      { timeout: 15000 }
    );
    await pasteMenuItem.click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible({ timeout: 5000 });
    await dialog.getByRole('button', { name: 'OK', exact: true }).click();
    await expect(dialog).not.toBeVisible({ timeout: 10000 });

    // Wait for paste operation to complete
    await pasteResponsePromise;

    // Navigate to List/Records module for Copy Target (page 24) to find the copied page
    const listModuleUrl = await getListModuleUrl(page, 24);
    await page.goto(listModuleUrl);
    const listFrame = page.frameLocator('iframe');
    await expect(listFrame.locator('h1')).toBeVisible({ timeout: 15000 });

    // Find the link to the copied page in the list
    const copiedPageLink = listFrame.locator('a', { hasText: 'Copy Source' }).first();
    await expect(copiedPageLink).toBeVisible({ timeout: 10000 });

    // Get the page ID from the link href (URL-encoded: edit%5Bpages%5D%5B56%5D)
    const href = await copiedPageLink.getAttribute('href');
    const copiedPageId = href?.match(/edit%5Bpages%5D%5B(\d+)%5D/)?.[1];
    expect(copiedPageId).toBeTruthy();

    // Navigate to edit the copied page and verify slug
    await page.goto(`/typo3/record/edit?edit[pages][${copiedPageId}]=edit`);
    const editFrame = page.frameLocator('iframe');
    await expect(editFrame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const hiddenField = editFrame.locator('.sluggi-hidden-field');
    const slug = await hiddenField.inputValue();
    expect(slug).toMatch(/^\/copy-target\/copy-source(-\d+)?$/);
  });
});
