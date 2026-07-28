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
async function pageTreeNodeLocator(page: Page, pageId: number | string): Promise<Locator> {
  const version = await getTypo3Version(page);
  const pageTree = await getPageTreeContainer(page);

  if (version === 12) {
    return pageTree.getByRole('treeitem', { name: new RegExp(`^id=${pageId} - `) }).first();
  }

  return pageTree.locator(`[data-id="${pageId}"]`).first();
}

/**
 * Locate a page tree node, scrolling it into view first.
 *
 * The tree grows as tests add pages, and TYPO3 12/13 virtualise it: an
 * off-screen node is not in the DOM at all, so the container has to be
 * scrolled until it renders.
 */
export async function getPageTreeNode(page: Page, pageId: number | string): Promise<Locator> {
  const node = await pageTreeNodeLocator(page, pageId);
  const wrapper = page.locator('.svg-tree-wrapper, .nodes-root').first();

  for (let step = 1; step <= 40; step++) {
    if (await node.count() > 0) {
      await node.scrollIntoViewIfNeeded({ timeout: 10000 });

      return node;
    }

    const reachedEnd = await wrapper.evaluate((element, index) => {
      const previous = element.scrollTop;
      element.scrollTop = index * Math.max(200, Math.round(element.clientHeight * 0.75));

      return element.scrollTop === previous;
    }, step);
    await page.waitForTimeout(150);

    if (reachedEnd) {
      break;
    }
  }

  await node.waitFor({ state: 'attached', timeout: 10000 });
  await node.scrollIntoViewIfNeeded({ timeout: 10000 });

  return node;
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
 *
 * TYPO3 14+: h1 is the page title (varies per record) — assert visibility only.
 * TYPO3 < 14: h1 contains "Edit Page".
 */
export async function waitForFormFrame(page: Page): Promise<ReturnType<Page['frameLocator']>> {
  const frame = page.frameLocator('iframe');
  const version = await getTypo3Version(page);

  if (version < 14) {
    await expect(frame.locator('h1')).toContainText('Edit Page', { timeout: 15000 });
  } else {
    await expect(frame.locator('h1').first()).toBeVisible({ timeout: 15000 });
  }

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

/**
 * Wait for an edit form to be loaded inside the iframe.
 *
 * TYPO3 14+: h1 visible (the page title, not a literal "Edit Page").
 * TYPO3 < 14: h1 contains "Edit Page".
 *
 * For records without sluggi-element (SysFolder etc.), use waitForEditFormWithoutSlug.
 */
export async function waitForEditForm(
  frame: ReturnType<Page['frameLocator']>,
  page: Page,
): Promise<void> {
  const version = await getTypo3Version(page);

  if (version < 14) {
    await expect(frame.locator('h1').first()).toContainText('Edit Page', { timeout: 15000 });
  } else {
    await expect(frame.locator('h1').first()).toBeVisible({ timeout: 15000 });
  }
}

/**
 * Wait for an edit form for a record type that has no sluggi-element
 * (e.g. SysFolder / excluded doktypes).
 *
 * TYPO3 14+: h1 visible + form visible.
 * TYPO3 < 14: h1 contains expectedH1Text (if given) + form visible.
 */
export async function waitForEditFormWithoutSlug(
  frame: ReturnType<Page['frameLocator']>,
  page: Page,
  expectedH1Text?: string,
): Promise<void> {
  const version = await getTypo3Version(page);

  if (version < 14 && expectedH1Text !== undefined) {
    await expect(frame.locator('h1')).toContainText(expectedH1Text, { timeout: 15000 });
  } else {
    await expect(frame.locator('h1').first()).toBeVisible({ timeout: 15000 });
  }

  await expect(frame.locator('form').first()).toBeVisible({ timeout: 10000 });
}

/**
 * Wait for a "Create new page" form inside the iframe.
 *
 * TYPO3 14+: h1 visible + title input visible.
 * TYPO3 < 14: h1 contains "Create new Page" + title input visible.
 */
export async function waitForNewPageForm(
  frame: ReturnType<Page['frameLocator']>,
  page: Page,
): Promise<void> {
  const version = await getTypo3Version(page);

  if (version < 14) {
    await expect(frame.locator('h1')).toContainText('Create new Page', { timeout: 15000 });
  } else {
    await expect(frame.locator('h1').first()).toBeVisible({ timeout: 15000 });
  }

  await expect(
    frame.locator('input[data-formengine-input-name*="[title]"]').first(),
  ).toBeVisible({ timeout: 10000 });
}

/**
 * Open a "New subpage" form for a given parent page.
 *
 * Navigates directly to the classic edit URL (`edit[pages][-{parentId}]=new`),
 * which on both TYPO3 12/13 and 14.2 renders the full-page edit form inside
 * the iframe. This deliberately bypasses TYPO3 14.2's PageCreationWizard
 * modal — we only need the new-page form for sluggi-element assertions.
 */
export async function openNewSubpageForm(
  page: Page,
  parentPageId: number | string,
): Promise<ReturnType<Page['frameLocator']>> {
  await page.goto(`/typo3/record/edit?edit[pages][${parentPageId}]=new`);
  const frame = page.frameLocator('iframe');
  await waitForNewPageForm(frame, page);
  return frame;
}

/**
 * Wait for a form save to fully complete (page reload).
 *
 * Thin wrapper over waitForLoadState for call-site readability.
 */
export async function waitForSaveComplete(page: Page): Promise<void> {
  await page.waitForLoadState('load');
}
