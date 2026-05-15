import {expect, test} from '@playwright/test';
import { expandPageTreeNode, getPageTreeItemByName, scrollPageTreeToBottom, waitForEditForm, waitForPageTree } from '../fixtures/typo3-compat';

test.describe('Recursive Slug Update - Context Menu', () => {
  test.use({
    // Use the redirect-control extension config so the redirect-notification-handler
    // module is loaded and the typo3:sluggi:slugChangeReport event produces a toast.
    extraHTTPHeaders: {
      'X-Playwright-Test-Id': 'redirect-control',
    },
  });

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

    // The recursive flow always reports redirectsCreated = 0 and uses the
    // cascadeRoot template — the toast title is "URL paths regenerated" and
    // the message names the parent page (title + UID) so editors know which
    // subtree was touched. "redirects created" must never appear.
    const toast = page.locator('.alert-info', { hasText: 'URL paths regenerated' }).first();
    await expect(toast).toBeVisible({ timeout: 10000 });
    await expect(toast).toContainText('Recursive Parent');
    await expect(toast).toContainText('UID 49');
    await expect(
      page.locator('.alert-info', { hasText: /redirects created/ })
    ).toHaveCount(0);
    await expect(toast.getByRole('button', { name: /Revert redirects/i })).toHaveCount(0);

    await page.goto('/typo3/record/edit?edit[pages][50]=edit');
    const frame = page.frameLocator('iframe');
    await waitForEditForm(frame, page);

    const hiddenField = frame.locator('.sluggi-hidden-field');
    await expect(hiddenField).toHaveValue('/recursive-parent/recursive-child');
  });
});
