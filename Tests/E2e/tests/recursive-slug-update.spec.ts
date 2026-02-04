import {expect, test} from '@playwright/test';
import { expandPageTreeNode, getPageTreeItemByName, scrollPageTreeToBottom, waitForPageTree } from '../fixtures/typo3-compat';

test.describe('Recursive Slug Update - Context Menu', () => {
  test('updates child slug via context menu and shows statistics notification', async ({ page }) => {
    await page.goto('/typo3/module/web/layout');
    await waitForPageTree(page);
    await expandPageTreeNode(page, 1);
    await scrollPageTreeToBottom(page);

    const parentNode = await getPageTreeItemByName(page, 'Recursive Parent');
    await expect(parentNode).toBeVisible({ timeout: 10000 });
    await parentNode.click({ button: 'right' });

    const moreOptions = page.getByRole('menuitem', { name: 'More options' });
    await expect(moreOptions).toBeVisible({ timeout: 5000 });
    await moreOptions.click();

    const recursiveItem = page.getByRole('menuitem', { name: /re-apply url paths/i });
    await expect(recursiveItem).toBeVisible({ timeout: 5000 });
    await recursiveItem.click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible({ timeout: 5000 });
    await dialog.getByRole('button', { name: 'Regenerate URL Paths', exact: true }).click();
    await expect(dialog).not.toBeVisible({ timeout: 5000 });

    const notification = page.locator('.alert-success');
    await expect(notification).toBeVisible({ timeout: 10000 });
    await expect(notification).toContainText('1 updated');

    await page.goto('/typo3/record/edit?edit[pages][50]=edit');
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

    const hiddenField = frame.locator('.sluggi-hidden-field');
    await expect(hiddenField).toHaveValue('/recursive-parent/recursive-child');
  });
});
