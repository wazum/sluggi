import {expect, test} from '@playwright/test';
import { expandPageTreeNode, getPageTreeItemByName, waitForEditForm, waitForPageTree } from '../fixtures/typo3-compat';

test.describe('Page Move - Slug Update', () => {
  test('moving a page into another updates slug with parent prefix', async ({ page }) => {
    await page.goto('/typo3/module/web/layout');
    await waitForPageTree(page);

    // Expand root node to show child pages
    await expandPageTreeNode(page, 1);

    const childNode = await getPageTreeItemByName(page, 'Child Page');
    await expect(childNode).toBeVisible({ timeout: 10000 });
    await childNode.click({ button: 'right' });
    const cutMenuItem = page.getByRole('menuitem', { name: 'Cut' });
    await cutMenuItem.click();
    await expect(cutMenuItem).not.toBeVisible();

    const parentNode = await getPageTreeItemByName(page, 'Parent Page');
    await expect(parentNode).toBeVisible({ timeout: 10000 });
    await parentNode.click({ button: 'right' });

    // Paste navigates the iframe to /record/commit (the tce_db route path shared
    // by TYPO3 12/13/14), which responds with a 303 redirect to the layout
    // module. Wait for that response so the edit form is opened only after the
    // move and its slug update have been processed, not while they are in flight.
    const pasteResponsePromise = page.waitForResponse(
      response => response.url().includes('/record/commit'),
      { timeout: 15000 }
    );
    await page.getByRole('menuitem', { name: 'Paste into' }).click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible({ timeout: 5000 });
    await dialog.getByRole('button', { name: 'OK', exact: true }).click();
    await expect(dialog).not.toBeVisible({ timeout: 5000 });

    await pasteResponsePromise;

    await page.goto('/typo3/record/edit?edit[pages][17]=edit');
    const frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);

    const hiddenField = frame.locator('.sluggi-hidden-field');
    const slug = await hiddenField.inputValue();
    expect(slug).toBe('/parent-page/child-page');
  });
});
