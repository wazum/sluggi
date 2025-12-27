import { Page, Locator, expect } from '@playwright/test';

/**
 * TYPO3 version compatibility layer for E2E tests.
 *
 * Contains ONLY functions that have different behavior between TYPO3 versions.
 * Do not add generic helper functions here.
 *
 * @deprecated Remove this file when dropping TYPO3 12 support
 */

let cachedVersion: number | null = null;

/**
 * Detect TYPO3 major version from the backend.
 */
export async function getTypo3Version(page: Page): Promise<number> {
  if (cachedVersion !== null) {
    return cachedVersion;
  }

  const version = await page.evaluate(() => {
    const text = document.body.textContent || '';
    const match = text.match(/(\d+)\.\d+\.\d+/);
    return match ? parseInt(match[1], 10) : 14;
  });

  cachedVersion = version;
  return version;
}

/**
 * Check if running on TYPO3 12 (legacy version).
 * Note: TYPO3 13.4+ uses the same page tree format as TYPO3 14.
 */
export async function isTypo3LegacyVersion(page: Page): Promise<boolean> {
  const version = await getTypo3Version(page);
  return version === 12;
}

/**
 * Get page tree node locator by page ID.
 *
 * TYPO3 12: Uses treeitem role with "id=X - Title" name pattern
 * TYPO3 13+: Uses data-id="X" attribute
 */
export async function getPageTreeNode(page: Page, pageId: number | string): Promise<Locator> {
  const pageTree = page.locator('.scaffold-content-navigation-component');
  const isLegacy = await isTypo3LegacyVersion(page);

  if (isLegacy) {
    return pageTree.getByRole('treeitem', { name: new RegExp(`^id=${pageId} - `) }).first();
  }

  return pageTree.locator(`[data-id="${pageId}"]`).first();
}

/**
 * Expand a page tree node by clicking its toggle.
 */
export async function expandPageTreeNode(page: Page, pageId: number | string): Promise<void> {
  const isLegacy = await isTypo3LegacyVersion(page);
  const pageTree = page.locator('.scaffold-content-navigation-component');

  if (isLegacy) {
    const node = page.locator(`#identifier-0_${pageId}`).first();
    await node.waitFor({ state: 'attached', timeout: 10000 });

    const isExpanded = await node.getAttribute('aria-expanded') === 'true';
    if (!isExpanded) {
      const toggle = node.locator('.node-toggle');
      await toggle.click({ force: true, position: { x: 8, y: 8 } });
      await expect(node).toHaveAttribute('aria-expanded', 'true', { timeout: 10000 });
    }
  } else {
    const node = pageTree.locator(`[data-id="${pageId}"]`);
    await expect(node).toBeVisible({ timeout: 10000 });

    const isExpanded = await node.getAttribute('aria-expanded') === 'true';
    if (!isExpanded) {
      const toggle = node.locator('.node-toggle');
      if (await toggle.isVisible()) {
        await toggle.click();
        await expect(node).toHaveAttribute('aria-expanded', 'true', { timeout: 10000 });
      }
    }
  }
}

/**
 * Get page tree item locator by page name.
 *
 * TYPO3 12: Tree items have "id=X - Name" format
 * TYPO3 13+: Tree items have just "Name" format
 */
export async function getPageTreeItemByName(page: Page, name: string | RegExp): Promise<Locator> {
  const isLegacy = await isTypo3LegacyVersion(page);

  if (isLegacy) {
    const pattern = typeof name === 'string'
      ? new RegExp(`id=\\d+ - ${name}`)
      : new RegExp(`id=\\d+ - ${name.source}`);
    return page.getByRole('treeitem', { name: pattern });
  }

  return page.getByRole('treeitem', { name });
}

/**
 * Get inline edit input locator for page tree.
 */
export async function getPageTreeEditInput(page: Page): Promise<Locator> {
  const pageTree = page.locator('.scaffold-content-navigation-component');
  return pageTree.locator('input.node-edit');
}

/**
 * Get content label locator for a page tree node (for double-click inline edit).
 */
export async function getPageTreeNodeLabel(page: Page, pageId: number | string): Promise<Locator> {
  return getPageTreeNode(page, pageId);
}

/**
 * Open page tree options menu and reload tree.
 */
export async function reloadPageTree(page: Page): Promise<void> {
  const isLegacy = await isTypo3LegacyVersion(page);
  const pageTree = page.locator('.scaffold-content-navigation-component');

  if (isLegacy) {
    await page.locator('#typo3-pagetree-toolbar').getByRole('button').click();
    await page.getByRole('button', { name: 'Reload the tree from server' }).click();
  } else {
    await page.getByRole('button', { name: 'Open page tree options menu' }).click();
    await page.getByRole('button', { name: 'Reload the tree from server' }).click();
  }

  await pageTree.locator('.node-loader').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});
  await expect(pageTree.locator('[role="treeitem"]').first()).toBeVisible({ timeout: 10000 });
}

/**
 * Wait for the form iframe to be fully loaded with sluggi-element initialized.
 */
export async function waitForFormFrame(page: Page): Promise<ReturnType<Page['frameLocator']>> {
  const frame = page.frameLocator('iframe');
  await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

  const slugElement = frame.locator('sluggi-element');
  await expect(slugElement.locator('.sluggi-editable')).toBeVisible({ timeout: 10000 });

  return frame;
}

/**
 * Wait for FormEngine to fully initialize all source field inputs.
 */
export async function waitForSourceFieldsInitialized(frame: ReturnType<Page['frameLocator']>): Promise<void> {
  const titleInput = frame.locator('input[data-sluggi-source][data-formengine-input-name*="[title]"]');
  await expect(titleInput).toHaveAttribute('data-formengine-input-initialized', 'true', { timeout: 10000 });
}

/**
 * Get the List/Records module URL for a page.
 *
 * TYPO3 14+: /typo3/module/content/records?id=X
 * TYPO3 12/13: /typo3/module/web/list?id=X
 */
export async function getListModuleUrl(page: Page, pageId: number | string): Promise<string> {
  const version = await getTypo3Version(page);
  if (version < 14) {
    return `/typo3/module/web/list?id=${pageId}`;
  }
  return `/typo3/module/content/records?id=${pageId}`;
}
