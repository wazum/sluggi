import {expect, test} from '@playwright/test';
import { expandPageTreeNode, getPageTreeItemByName } from '../fixtures/typo3-compat';

test.describe('Page Move - Slug Update', () => {
  test('moving a page into another updates slug with parent prefix', async ({ page }) => {
    await page.goto('/typo3/module/web/layout');
    const pageTree = page.locator('.scaffold-content-navigation-component');
    await expect(pageTree).toBeVisible({ timeout: 15000 });

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
    await page.getByRole('menuitem', { name: 'Paste into' }).click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible({ timeout: 5000 });
    await dialog.getByRole('button', { name: 'OK', exact: true }).click();
    await expect(dialog).not.toBeVisible({ timeout: 5000 });

    await page.goto('/typo3/record/edit?edit[pages][17]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const hiddenField = frame.locator('.sluggi-hidden-field');
    const slug = await hiddenField.inputValue();
    expect(slug).toBe('/parent-page/child-page');
  });
});
