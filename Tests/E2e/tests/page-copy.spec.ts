import { test, expect } from '@playwright/test';
import { expandPageTreeNode, getPageTreeNode, getListModuleUrl, waitForEditForm, waitForPageTree } from '../fixtures/typo3-compat';

test.describe('Page Copy - Slug Update', () => {
  test('copying a page into another updates slug with parent prefix', async ({ page }) => {
    await page.goto('/typo3/module/web/layout');
    await waitForPageTree(page);

    await expandPageTreeNode(page, 1);

    const sourceNode = await getPageTreeNode(page, 23);
    await sourceNode.click({ button: 'right' });

    const copyMenuItem = page.getByRole('menuitem', { name: 'Copy' });
    await expect(copyMenuItem).toBeVisible({ timeout: 5000 });
    await copyMenuItem.click();
    await expect(copyMenuItem).not.toBeVisible({ timeout: 5000 });

    const targetNode = await getPageTreeNode(page, 24);
    await targetNode.click({ button: 'right' });

    const pasteMenuItem = page.getByRole('menuitem', { name: 'Paste into' });
    await expect(pasteMenuItem).toBeVisible({ timeout: 5000 });

    // Paste navigates the iframe to /record/commit (the tce_db route path shared
    // by TYPO3 12/13/14), which responds with a 303 redirect to the layout
    // module. Match the /record/commit response regardless of status so we wait
    // for the real paste, not an unrelated dialog/tree-refresh call.
    const pasteResponsePromise = page.waitForResponse(
      response => response.url().includes('/record/commit'),
      { timeout: 15000 }
    );
    await pasteMenuItem.click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible({ timeout: 5000 });
    await dialog.getByRole('button', { name: 'OK', exact: true }).click();
    await expect(dialog).not.toBeVisible({ timeout: 10000 });

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
    await waitForEditForm(editFrame, page);

    const hiddenField = editFrame.locator('.sluggi-hidden-field');
    const slug = await hiddenField.inputValue();
    expect(slug).toMatch(/^\/copy-target\/copy-source(-\d+)?$/);
  });
});
