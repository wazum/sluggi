import { Page, Locator, expect } from '@playwright/test';

/**
 * TYPO3 version compatibility layer for E2E tests.
 *
 * Contains ONLY functions that have different behavior between TYPO3 versions.
 * Do not add generic helper functions here.
 *
 * @deprecated Remove this file when dropping TYPO3 12/13 support
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
 * Check if running on TYPO3 12 or 13 (legacy versions).
 */
export async function isTypo3LegacyVersion(page: Page): Promise<boolean> {
  const version = await getTypo3Version(page);
  return version === 12 || version === 13;
}

/**
 * Get page tree node locator by page ID.
 *
 * TYPO3 12/13: Uses treeitem role with "id=X - Title" name pattern
 * TYPO3 14: Uses data-id="X" attribute
 *
 * Note: Returns first match to handle mount point duplicates
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
 *
 * TYPO3 12/13: Uses SVG elements, requires special handling
 * TYPO3 14: Uses web components, standard click works
 */
export async function expandPageTreeNode(page: Page, pageId: number | string): Promise<void> {
  const isLegacy = await isTypo3LegacyVersion(page);

  if (isLegacy) {
    // Wait for the tree to be loaded - use first() in case of mount point duplicates
    const node = page.locator(`#identifier-0_${pageId}`).first();
    await node.waitFor({ state: 'attached', timeout: 10000 });

    // Check if already expanded
    const isExpanded = await node.getAttribute('aria-expanded') === 'true';

    if (!isExpanded) {
      // Click the toggle chevron SVG directly using Playwright
      const toggle = node.locator('.node-toggle');
      await toggle.click({ force: true, position: { x: 8, y: 8 } });
      // Wait for tree expansion
      await page.waitForTimeout(500);
    }
  } else {
    // TYPO3 14: Uses web components with data-id attribute
    const pageTree = page.locator('.scaffold-content-navigation-component');
    const node = pageTree.locator(`[data-id="${pageId}"]`);
    // Use Playwright's expect for auto-retry behavior (matches original test code)
    await expect(node).toBeVisible({ timeout: 10000 });
    const toggle = node.locator('.node-toggle');
    if (await toggle.isVisible()) {
      await toggle.click();
    }
  }
}

/**
 * Get page tree item locator by page name.
 *
 * TYPO3 12/13: Tree items have "id=X - Name" format
 * TYPO3 14: Tree items have just "Name" format
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
 *
 * TYPO3 12/13: Uses input.node-edit inside the SVG tree
 * TYPO3 14: Uses input.node-edit inside web components
 */
export async function getPageTreeEditInput(page: Page): Promise<Locator> {
  const pageTree = page.locator('.scaffold-content-navigation-component');
  return pageTree.locator('input.node-edit');
}

/**
 * Get content label locator for a page tree node (for double-click inline edit).
 *
 * TYPO3 12/13: Uses the node itself (SVG g element)
 * TYPO3 14: Uses .node-contentlabel
 *
 * Note: Returns first match to handle mount point duplicates
 */
export async function getPageTreeNodeLabel(page: Page, pageId: number | string): Promise<Locator> {
  // Reuse getPageTreeNode since in TYPO3 12 the node itself is clickable
  return getPageTreeNode(page, pageId);
}

/**
 * Open page tree options menu and reload tree.
 *
 * TYPO3 12/13: Uses toolbar button that opens dropdown
 * TYPO3 14: Uses button with "Open page tree options menu" name
 */
export async function reloadPageTree(page: Page): Promise<void> {
  const isLegacy = await isTypo3LegacyVersion(page);

  if (isLegacy) {
    // Click the options button in the page tree toolbar
    await page.locator('#typo3-pagetree-toolbar').getByRole('button').click();
    // Click the reload button in the dropdown
    await page.getByRole('button', { name: 'Reload the tree from server' }).click();
    // Wait for tree to reload
    await page.waitForTimeout(500);
  } else {
    await page.getByRole('button', { name: 'Open page tree options menu' }).click();
    await page.getByRole('button', { name: 'Reload the tree from server' }).click();
  }
}

/**
 * Wait for the form iframe to be fully loaded with sluggi-element initialized.
 *
 * TYPO3 12 loads the iframe content more slowly, so we need to ensure:
 * 1. The iframe exists and has content
 * 2. The h1 "Edit Page" is visible
 * 3. The sluggi-element shadow DOM is initialized (.sluggi-editable visible)
 */
export async function waitForFormFrame(page: Page): Promise<ReturnType<Page['frameLocator']>> {
  const frame = page.frameLocator('iframe');

  // Wait for h1 to contain Edit Page (form has loaded)
  await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });

  // Wait for sluggi-element shadow DOM to be initialized
  const slugElement = frame.locator('sluggi-element');
  await expect(slugElement.locator('.sluggi-editable')).toBeVisible({ timeout: 10000 });

  return frame;
}
