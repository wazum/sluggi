import { Page, Locator, expect } from '@playwright/test';

/**
 * TYPO3 version compatibility layer for E2E tests.
 *
 * TYPO3 14 is the default. Legacy handling for TYPO3 12/13.
 *
 * @deprecated Remove legacy code when dropping TYPO3 12 support
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
 * @deprecated TYPO3 12 support will be removed
 */
export async function isTypo3LegacyVersion(page: Page): Promise<boolean> {
  const version = await getTypo3Version(page);
  return version === 12;
}

/**
 * Get page tree container locator.
 *
 * TYPO3 14+: Uses web component typo3-backend-navigation-component-pagetree
 * TYPO3 12/13: Uses .scaffold-content-navigation-component class
 */
export async function getPageTreeContainer(page: Page): Promise<Locator> {
  const version = await getTypo3Version(page);

  if (version < 14) {
    return page.locator('.scaffold-content-navigation-component');
  }

  return page.locator('typo3-backend-navigation-component-pagetree');
}

/**
 * Wait for the page tree to be visible.
 */
export async function waitForPageTree(page: Page, timeout = 15000): Promise<void> {
  const version = await getTypo3Version(page);

  if (version < 14) {
    await expect(page.locator('.scaffold-content-navigation-component')).toBeVisible({ timeout });
  } else {
    await expect(page.getByRole('tree')).toBeVisible({ timeout });
  }
}

/**
 * Click a module menu item by name.
 *
 * TYPO3 14+: Uses role-based selector
 * TYPO3 12/13: Uses data-modulemenu-identifier attribute
 */
export async function clickModuleMenuItem(page: Page, name: string, moduleIdentifier?: string): Promise<void> {
  const version = await getTypo3Version(page);

  if (version < 14) {
    const identifier = moduleIdentifier || `web_${name.toLowerCase()}`;
    await page.click(`.scaffold-modulemenu [data-modulemenu-identifier="${identifier}"]`);
  } else {
    await page.getByRole('menuitem', { name }).click();
  }
}

/**
 * Get page tree node locator by page ID.
 *
 * TYPO3 12: Uses treeitem role with "id=X - Title" name pattern
 * TYPO3 13+: Uses data-id="X" attribute
 */
export async function getPageTreeNode(page: Page, pageId: number | string): Promise<Locator> {
  const version = await getTypo3Version(page);
  const pageTree = await getPageTreeContainer(page);

  if (version === 12) {
    return pageTree.getByRole('treeitem', { name: new RegExp(`^id=${pageId} - `) }).first();
  }

  return pageTree.locator(`[data-id="${pageId}"]`).first();
}

/**
 * Expand a page tree node by clicking its toggle.
 */
export async function expandPageTreeNode(page: Page, pageId: number | string): Promise<void> {
  const version = await getTypo3Version(page);

  if (version === 12) {
    const node = page.locator(`#identifier-0_${pageId}`).first();
    await node.waitFor({ state: 'attached', timeout: 10000 });

    const isExpanded = await node.getAttribute('aria-expanded') === 'true';
    if (!isExpanded) {
      const toggle = node.locator('.node-toggle');
      await toggle.click({ force: true, position: { x: 8, y: 8 } });
      await expect(node).toHaveAttribute('aria-expanded', 'true', { timeout: 10000 });
    }
  } else {
    const pageTree = await getPageTreeContainer(page);
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
 * Scroll the page tree to make bottom items visible.
 *
 * TYPO3 12/13 use SVG-based virtual rendering that only renders
 * visible nodes. Scrolling the container triggers rendering of
 * off-screen nodes.
 */
export async function scrollPageTreeToBottom(page: Page): Promise<void> {
  const version = await getTypo3Version(page);
  if (version >= 14) {
    return;
  }

  await page.evaluate(async () => {
    const wrapper = document.querySelector('.svg-tree-wrapper');
    if (wrapper) {
      wrapper.scrollTop = wrapper.scrollHeight;
      await new Promise(r => setTimeout(r, 300));
    }
  });
}

/**
 * Get page tree item locator by page name.
 *
 * TYPO3 12: Tree items have "id=X - Name" format
 * TYPO3 13+: Tree items have just "Name" format
 */
export async function getPageTreeItemByName(page: Page, name: string | RegExp): Promise<Locator> {
  const version = await getTypo3Version(page);

  if (version === 12) {
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
  const pageTree = await getPageTreeContainer(page);
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
  const version = await getTypo3Version(page);

  if (version === 12) {
    await page.locator('#typo3-pagetree-toolbar').getByRole('button').click();
  } else {
    await page.getByRole('button', { name: 'Open page tree options menu' }).click();
  }

  await page.getByRole('button', { name: 'Reload the tree from server' }).click();

  const pageTree = await getPageTreeContainer(page);
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

/**
 * Get the multi-record edit URL with columnsOnly parameter.
 *
 * TYPO3 12: columnsOnly is a comma-separated string (columnsOnly=field1,field2)
 * TYPO3 13+: columnsOnly is a per-table array (columnsOnly[table][0]=field1&columnsOnly[table][1]=field2)
 */
export async function getMultiEditUrl(
  page: Page,
  table: string,
  uids: (number | string)[],
  columns: string[]
): Promise<string> {
  const version = await getTypo3Version(page);
  const uidList = uids.join(',');
  const baseUrl = `/typo3/record/edit?edit[${table}][${uidList}]=edit`;

  if (version < 13) {
    return `${baseUrl}&columnsOnly=${columns.join(',')}`;
  }

  const columnsParams = columns
    .map((col, idx) => `columnsOnly[${table}][${idx}]=${col}`)
    .join('&');
  return `${baseUrl}&${columnsParams}`;
}
