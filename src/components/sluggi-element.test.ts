import { expect, fixture, html } from '@open-wc/testing';
import './sluggi-element.js';
import type { SluggiElement } from './sluggi-element.js';

function setupTYPO3Global() {
    (window as unknown as Record<string, unknown>).TYPO3 = {
        settings: { ajaxUrls: { record_slug_suggest: '/api/slug-suggest' } },
        lang: {},
    };
}

function createSiblingFields(container: HTMLElement, recordId: string, table = 'pages') {
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.className = 'sluggi-hidden-field';
    hidden.name = `data[${table}][${recordId}][slug]`;
    container.appendChild(hidden);

    const sync = document.createElement('input');
    sync.type = 'hidden';
    sync.className = 'sluggi-sync-field';
    sync.name = `data[${table}][${recordId}][tx_sluggi_sync]`;
    sync.value = '0';
    container.appendChild(sync);

    const lock = document.createElement('input');
    lock.type = 'hidden';
    lock.className = 'sluggi-lock-field';
    lock.name = `data[${table}][${recordId}][slug_locked]`;
    lock.value = '0';
    container.appendChild(lock);

    const fullPath = document.createElement('input');
    fullPath.type = 'hidden';
    fullPath.className = 'sluggi-full-path-field';
    fullPath.value = '0';
    container.appendChild(fullPath);

    return { hidden, sync, lock, fullPath };
}

describe('sluggi-element new page placeholder', () => {
    before(() => {
        setupTYPO3Global();
    });

    it('should not duplicate the leading slash in the placeholder for a new page (admin)', async () => {
        const container = await fixture<HTMLDivElement>(html`
            <div>
                <sluggi-element
                    value=""
                    command="new"
                    page-id="0"
                    record-id="NEW1"
                    table-name="pages"
                    field-name="slug"
                    language="0"
                    signature="test"
                    parent-page-id="1"
                ></sluggi-element>
            </div>
        `);
        const el = container.querySelector('sluggi-element') as SluggiElement;
        createSiblingFields(container, 'NEW1');

        const sourceInput = document.createElement('input');
        sourceInput.type = 'text';
        sourceInput.setAttribute('data-formengine-input-name', 'data[pages][NEW1][title]');
        sourceInput.setAttribute('data-sluggi-source', '');
        sourceInput.setAttribute('data-formengine-input-initialized', 'true');
        container.appendChild(sourceInput);

        el.disconnectedCallback();
        el.connectedCallback();
        await el.updateComplete;

        const shadowRoot = el.shadowRoot!;
        const placeholder = shadowRoot.querySelector('.sluggi-placeholder');
        const editableEnd = shadowRoot.querySelector('.sluggi-editable-end');

        expect(placeholder).to.not.be.null;
        expect(editableEnd!.textContent).to.equal('/');
        expect(placeholder!.textContent).to.equal('new-page');
    });

    it('should not duplicate the prefix for a new page with lastSegmentOnly', async () => {
        const container = await fixture<HTMLDivElement>(html`
            <div>
                <sluggi-element
                    value="/parent-section"
                    locked-prefix="/parent-section"
                    last-segment-only
                    command="new"
                    page-id="0"
                    record-id="NEW2"
                    table-name="pages"
                    field-name="slug"
                    language="0"
                    signature="test"
                    parent-page-id="18"
                ></sluggi-element>
            </div>
        `);
        const el = container.querySelector('sluggi-element') as SluggiElement;
        createSiblingFields(container, 'NEW2');

        const sourceInput = document.createElement('input');
        sourceInput.type = 'text';
        sourceInput.setAttribute('data-formengine-input-name', 'data[pages][NEW2][title]');
        sourceInput.setAttribute('data-sluggi-source', '');
        sourceInput.setAttribute('data-formengine-input-initialized', 'true');
        container.appendChild(sourceInput);

        el.disconnectedCallback();
        el.connectedCallback();
        await el.updateComplete;

        const shadowRoot = el.shadowRoot!;
        const prefix = shadowRoot.querySelector('.sluggi-prefix');
        const editableEnd = shadowRoot.querySelector('.sluggi-editable-end');
        const editablePath = shadowRoot.querySelector('.sluggi-editable-path');

        expect(prefix!.textContent).to.equal('/parent-section');
        expect(editablePath).to.be.null;
        expect(editableEnd!.textContent).to.equal('/');
    });

    it('should not duplicate the prefix for a new page with lockedPrefix (hierarchy permissions)', async () => {
        const container = await fixture<HTMLDivElement>(html`
            <div>
                <sluggi-element
                    value="/parent-section"
                    locked-prefix="/parent-section"
                    command="new"
                    page-id="0"
                    record-id="NEW3"
                    table-name="pages"
                    field-name="slug"
                    language="0"
                    signature="test"
                    parent-page-id="18"
                ></sluggi-element>
            </div>
        `);
        const el = container.querySelector('sluggi-element') as SluggiElement;
        createSiblingFields(container, 'NEW3');

        const sourceInput = document.createElement('input');
        sourceInput.type = 'text';
        sourceInput.setAttribute('data-formengine-input-name', 'data[pages][NEW3][title]');
        sourceInput.setAttribute('data-sluggi-source', '');
        sourceInput.setAttribute('data-formengine-input-initialized', 'true');
        container.appendChild(sourceInput);

        el.disconnectedCallback();
        el.connectedCallback();
        await el.updateComplete;

        const shadowRoot = el.shadowRoot!;
        const prefix = shadowRoot.querySelector('.sluggi-prefix');
        const editableEnd = shadowRoot.querySelector('.sluggi-editable-end');
        const editablePath = shadowRoot.querySelector('.sluggi-editable-path');

        expect(prefix!.textContent).to.equal('/parent-section');
        expect(editablePath).to.be.null;
        expect(editableEnd!.textContent).to.equal('/');
    });
});

describe('sluggi-element regenerateWouldLeaveLockedHierarchy', () => {
    before(() => {
        setupTYPO3Global();
    });

    async function makeElement(attrs: Record<string, string>): Promise<SluggiElement> {
        const container = await fixture<HTMLDivElement>(html`<div></div>`);
        createSiblingFields(container, attrs['record-id'] ?? '1');
        const el = document.createElement('sluggi-element') as SluggiElement;
        for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
        container.appendChild(el);
        await el.updateComplete;
        return el;
    }

    it('returns false when lockedPrefix is empty', async () => {
        const el = await makeElement({
            value: '/parent/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'parent-slug': '/parent',
        });
        expect((el as unknown as { regenerateWouldLeaveLockedHierarchy: boolean }).regenerateWouldLeaveLockedHierarchy).to.equal(false);
    });

    it('returns false when parentSlug is empty', async () => {
        const el = await makeElement({
            value: '/parent/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/parent',
        });
        expect((el as unknown as { regenerateWouldLeaveLockedHierarchy: boolean }).regenerateWouldLeaveLockedHierarchy).to.equal(false);
    });

    it('returns false when parentSlug equals lockedPrefix', async () => {
        const el = await makeElement({
            value: '/parent/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/parent', 'parent-slug': '/parent',
        });
        expect((el as unknown as { regenerateWouldLeaveLockedHierarchy: boolean }).regenerateWouldLeaveLockedHierarchy).to.equal(false);
    });

    it('returns false when parentSlug is under lockedPrefix', async () => {
        const el = await makeElement({
            value: '/parent/sub/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/parent', 'parent-slug': '/parent/sub',
        });
        expect((el as unknown as { regenerateWouldLeaveLockedHierarchy: boolean }).regenerateWouldLeaveLockedHierarchy).to.equal(false);
    });

    it('returns true when parentSlug crosses lockedPrefix', async () => {
        const el = await makeElement({
            value: '/old-folder/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/old-folder', 'parent-slug': '/new-folder',
        });
        expect((el as unknown as { regenerateWouldLeaveLockedHierarchy: boolean }).regenerateWouldLeaveLockedHierarchy).to.equal(true);
    });

    it('normalizes trailing slashes on both inputs', async () => {
        const el = await makeElement({
            value: '/x/y', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/parent/', 'parent-slug': '/parent',
        });
        expect((el as unknown as { regenerateWouldLeaveLockedHierarchy: boolean }).regenerateWouldLeaveLockedHierarchy).to.equal(false);
    });

    it('returns false when fullPathFeatureEnabled is true even if would cross', async () => {
        const el = await makeElement({
            value: '/old/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/old', 'parent-slug': '/new',
            'full-path-feature-enabled': '',
        });
        expect((el as unknown as { regenerateWouldLeaveLockedHierarchy: boolean }).regenerateWouldLeaveLockedHierarchy).to.equal(false);
    });
});

describe('sluggi-element isRegenerateDisabled', () => {
    before(() => {
        setupTYPO3Global();
    });

    async function makeElement(attrs: Record<string, string>): Promise<SluggiElement> {
        const container = await fixture<HTMLDivElement>(html`<div></div>`);
        createSiblingFields(container, attrs['record-id'] ?? '1');
        const el = document.createElement('sluggi-element') as SluggiElement;
        for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
        container.appendChild(el);
        await el.updateComplete;
        return el;
    }

    it('is true when regenerate would cross the locked hierarchy', async () => {
        const el = await makeElement({
            value: '/old/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/old', 'parent-slug': '/new',
        });
        expect((el as unknown as { isRegenerateDisabled: boolean }).isRegenerateDisabled).to.equal(true);
    });

    it('is true even when hasPostModifiers is set if would cross', async () => {
        const el = await makeElement({
            value: '/old/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/old', 'parent-slug': '/new',
            'has-post-modifiers': '',
        });
        expect((el as unknown as { isRegenerateDisabled: boolean }).isRegenerateDisabled).to.equal(true);
    });

    it('is false when in-hierarchy and hasPostModifiers (existing behavior preserved)', async () => {
        const el = await makeElement({
            value: '/parent/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/parent', 'parent-slug': '/parent',
            'has-post-modifiers': '',
        });
        expect((el as unknown as { isRegenerateDisabled: boolean }).isRegenerateDisabled).to.equal(false);
    });
});

describe('sluggi-element isSyncToggleDisabled (asymmetric for hierarchy cross)', () => {
    before(() => {
        setupTYPO3Global();
    });

    async function makeElement(attrs: Record<string, string>): Promise<SluggiElement> {
        const container = await fixture<HTMLDivElement>(html`<div></div>`);
        createSiblingFields(container, attrs['record-id'] ?? '1');
        const el = document.createElement('sluggi-element') as SluggiElement;
        for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
        container.appendChild(el);
        await el.updateComplete;
        return el;
    }

    it('is true when sync is off and regen would cross', async () => {
        const el = await makeElement({
            value: '/old/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/old', 'parent-slug': '/new',
            'sync-feature-enabled': '',
        });
        expect((el as unknown as { isSyncToggleDisabled: boolean }).isSyncToggleDisabled).to.equal(true);
    });

    it('is false when sync is on and regen would cross (allow disabling)', async () => {
        const el = await makeElement({
            value: '/old/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/old', 'parent-slug': '/new',
            'sync-feature-enabled': '', 'is-synced': '',
        });
        expect((el as unknown as { isSyncToggleDisabled: boolean }).isSyncToggleDisabled).to.equal(false);
    });

    it('is false when sync is off and regen would NOT cross (toggle remains available)', async () => {
        const el = await makeElement({
            value: '/parent/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/parent', 'parent-slug': '/parent',
            'sync-feature-enabled': '',
        });
        expect((el as unknown as { isSyncToggleDisabled: boolean }).isSyncToggleDisabled).to.equal(false);
    });
});

describe('sluggi-element handleSourceFieldChange (hierarchy guard)', () => {
    before(() => {
        setupTYPO3Global();
    });

    it('does NOT call sendSlugProposal when synced and regen would cross', async () => {
        const container = await fixture<HTMLDivElement>(html`<div></div>`);
        createSiblingFields(container, '1');
        const el = document.createElement('sluggi-element') as SluggiElement;
        Object.entries({
            value: '/old/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/old', 'parent-slug': '/new',
            'sync-feature-enabled': '', 'is-synced': '',
        }).forEach(([k, v]) => el.setAttribute(k, v));
        container.appendChild(el);
        await el.updateComplete;

        // Witness: the new hierarchy guard is the cause of the zero call count
        // (not the earlier empty-value or required-fields short-circuits).
        expect((el as unknown as { regenerateWouldLeaveLockedHierarchy: boolean }).regenerateWouldLeaveLockedHierarchy).to.equal(true);

        let proposalCalls = 0;
        (el as unknown as { sendSlugProposal: (kind: string) => void }).sendSlugProposal =
            () => { proposalCalls += 1; };

        const fakeEvent = { target: { value: 'Hello' } } as unknown as Event;
        (el as unknown as { handleSourceFieldChange: (e: Event) => void }).handleSourceFieldChange(fakeEvent);

        expect(proposalCalls).to.equal(0);
    });

    it('DOES call sendSlugProposal when synced and regen would NOT cross', async () => {
        const container = await fixture<HTMLDivElement>(html`<div></div>`);
        createSiblingFields(container, '1');
        const el = document.createElement('sluggi-element') as SluggiElement;
        Object.entries({
            value: '/parent/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/parent', 'parent-slug': '/parent',
            'sync-feature-enabled': '', 'is-synced': '',
        }).forEach(([k, v]) => el.setAttribute(k, v));
        container.appendChild(el);
        await el.updateComplete;

        let proposalCalls = 0;
        (el as unknown as { sendSlugProposal: (kind: string) => void }).sendSlugProposal =
            () => { proposalCalls += 1; };

        const fakeEvent = { target: { value: 'Hello' } } as unknown as Event;
        (el as unknown as { handleSourceFieldChange: (e: Event) => void }).handleSourceFieldChange(fakeEvent);

        expect(proposalCalls).to.equal(1);
    });
});

describe('sluggi-element cannotRegenerateAdviceKey', () => {
    before(() => {
        setupTYPO3Global();
    });

    async function makeElement(attrs: Record<string, string>): Promise<SluggiElement> {
        const container = await fixture<HTMLDivElement>(html`<div></div>`);
        createSiblingFields(container, attrs['record-id'] ?? '1');
        const el = document.createElement('sluggi-element') as SluggiElement;
        for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
        container.appendChild(el);
        await el.updateComplete;
        return el;
    }
    function key(el: SluggiElement): string | null {
        return (el as unknown as { cannotRegenerateAdviceKey: string | null }).cannotRegenerateAdviceKey;
    }

    const baseDeadlock = {
        value: '/old/child', 'record-id': '1', 'page-id': '1',
        'table-name': 'pages', 'field-name': 'slug', language: '0',
        signature: 'x', 'parent-page-id': '1', command: 'edit',
        'locked-prefix': '/old', 'parent-slug': '/new',
    };

    it('returns null when not in deadlock', async () => {
        const el = await makeElement({ ...baseDeadlock, 'parent-slug': '/old' });
        expect(key(el)).to.equal(null);
    });

    it('returns null when locked', async () => {
        const el = await makeElement({ ...baseDeadlock, 'is-locked': '' });
        expect(key(el)).to.equal(null);
    });

    it('returns disableSync key when synced and toggle is enabled', async () => {
        const el = await makeElement({ ...baseDeadlock, 'sync-feature-enabled': '', 'is-synced': '' });
        expect(key(el)).to.equal('prefixMismatch.cannotRegenerate.disableSync');
    });

    it('returns lock key when not synced and lock toggle is available', async () => {
        const el = await makeElement({ ...baseDeadlock, 'lock-feature-enabled': '' });
        expect(key(el)).to.equal('prefixMismatch.cannotRegenerate.lock');
    });

    it('returns askAdmin key when not synced and lock is not available', async () => {
        const el = await makeElement({ ...baseDeadlock });
        expect(key(el)).to.equal('prefixMismatch.cannotRegenerate.askAdmin');
    });

    it('falls through to askAdmin for translations regardless of sync/lock state', async () => {
        const el = await makeElement({
            ...baseDeadlock,
            'is-translation': '',
            'sync-feature-enabled': '', 'is-synced': '',
            'lock-feature-enabled': '',
        });
        expect(key(el)).to.equal('prefixMismatch.cannotRegenerate.askAdmin');
    });

    it('showCannotRegenerateAdvice mirrors key non-null state', async () => {
        const elOff = await makeElement({ ...baseDeadlock, 'parent-slug': '/old' });
        const elOn = await makeElement({ ...baseDeadlock });
        expect((elOff as unknown as { showCannotRegenerateAdvice: boolean }).showCannotRegenerateAdvice).to.equal(false);
        expect((elOn as unknown as { showCannotRegenerateAdvice: boolean }).showCannotRegenerateAdvice).to.equal(true);
    });
});

describe('sluggi-element messaging surfaces in deadlock', () => {
    before(() => {
        setupTYPO3Global();
    });

    async function makeDeadlockElement(extra: Record<string, string> = {}): Promise<SluggiElement> {
        const container = await fixture<HTMLDivElement>(html`<div></div>`);
        createSiblingFields(container, '1');
        const el = document.createElement('sluggi-element') as SluggiElement;
        Object.entries({
            value: '/old/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/old', 'parent-slug': '/new',
            'lock-feature-enabled': '',
            labels: JSON.stringify({
                'prefixMismatch.cannotRegenerate.lock': "Lock the URL so future edits don't change it.",
                'prefixMismatch.tooltip': 'URL prefix mismatch.',
            }),
            ...extra,
        }).forEach(([k, v]) => el.setAttribute(k, v));
        container.appendChild(el);
        await el.updateComplete;
        return el;
    }

    it('renders the amber-tint class for synced-on deadlock (where hasPrefixMismatch is false)', async () => {
        const el = await makeDeadlockElement({ 'sync-feature-enabled': '', 'is-synced': '' });
        const prefix = el.shadowRoot!.querySelector('.sluggi-prefix');
        expect(prefix?.classList.contains('is-out-of-sync')).to.equal(true);
    });

    it('uses cannotRegenerate label as amber-prefix tooltip', async () => {
        const el = await makeDeadlockElement();
        const prefix = el.shadowRoot!.querySelector('.sluggi-prefix') as HTMLElement;
        expect(prefix?.title).to.equal("Lock the URL so future edits don't change it.");
    });

    it('renders the cannotRegenerate inline note text', async () => {
        const el = await makeDeadlockElement();
        const note = el.shadowRoot!.querySelector('.sluggi-restriction-note, .sluggi-prefix-mismatch-note, .sluggi-note');
        expect(note?.textContent).to.contain("Lock the URL so future edits don't change it.");
    });
});

describe('sluggi-element disabled-sync-toggle tooltip', () => {
    before(() => {
        setupTYPO3Global();
    });

    it('shows the cannot-regenerate label as title when sync toggle is disabled by the predicate', async () => {
        const container = await fixture<HTMLDivElement>(html`<div></div>`);
        createSiblingFields(container, '1');
        const sourceInput = document.createElement('input');
        sourceInput.type = 'text';
        sourceInput.setAttribute('data-formengine-input-name', 'data[pages][1][title]');
        sourceInput.setAttribute('data-sluggi-source', '');
        sourceInput.setAttribute('data-formengine-input-initialized', 'true');
        container.appendChild(sourceInput);
        const el = document.createElement('sluggi-element') as SluggiElement;
        Object.entries({
            value: '/old/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/old', 'parent-slug': '/new',
            'sync-feature-enabled': '', 'lock-feature-enabled': '',
            labels: JSON.stringify({
                'prefixMismatch.cannotRegenerate.lock': "Lock the URL so future edits don't change it.",
            }),
        }).forEach(([k, v]) => el.setAttribute(k, v));
        container.appendChild(el);
        await el.updateComplete;
        const button = el.shadowRoot!.querySelector('.sluggi-sync-toggle') as HTMLButtonElement;
        expect(button.disabled).to.equal(true);
        expect(button.title).to.equal("Lock the URL so future edits don't change it.");
    });
});

describe('sluggi-element source-confirm button (deadlock guard + scoping)', () => {
    before(() => {
        setupTYPO3Global();
    });

    async function makeWithSourceField(recordId: string, attrs: Record<string, string>): Promise<{el: SluggiElement; sourceInput: HTMLInputElement; confirmBtn: HTMLButtonElement}> {
        const container = await fixture<HTMLDivElement>(html`
            <div>
                <div class="sluggi-source-group sluggi-source-group--active">
                    <input
                        type="text"
                        data-sluggi-source
                        data-formengine-input-name="data[pages][${recordId}][title]"
                        data-formengine-input-initialized
                        value="Hello"
                    />
                    <button type="button" class="sluggi-source-confirm" title="Update URL path now"></button>
                </div>
            </div>
        `);
        createSiblingFields(container, recordId);
        const el = document.createElement('sluggi-element') as SluggiElement;
        Object.entries(attrs).forEach(([k, v]) => el.setAttribute(k, v));
        container.appendChild(el);
        await el.updateComplete;
        const sourceInput = container.querySelector('input[data-sluggi-source]') as HTMLInputElement;
        const confirmBtn = container.querySelector('.sluggi-source-confirm') as HTMLButtonElement;
        return { el, sourceInput, confirmBtn };
    }

    it('does NOT call sendSlugProposal on confirm click in deadlock', async () => {
        const { el, confirmBtn } = await makeWithSourceField('1', {
            value: '/old/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/old', 'parent-slug': '/new',
            'sync-feature-enabled': '', 'is-synced': '',
        });
        let calls = 0;
        (el as unknown as { sendSlugProposal: (k: string) => void }).sendSlugProposal = () => { calls += 1; };
        confirmBtn.click();
        expect(calls).to.equal(0);
    });

    it('disables confirm button + overrides title in deadlock (initial load)', async () => {
        const { confirmBtn } = await makeWithSourceField('1', {
            value: '/old/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/old', 'parent-slug': '/new',
            'sync-feature-enabled': '', 'is-synced': '',
            'lock-feature-enabled': '',
            labels: JSON.stringify({
                'prefixMismatch.cannotRegenerate.disableSync': "Auto-sync is on but won't update this URL — turn it off to make this explicit.",
                'sourceConfirm.title': 'Update URL path now',
            }),
        });
        expect(confirmBtn.disabled).to.equal(true);
        expect(confirmBtn.getAttribute('aria-disabled')).to.equal('true');
        expect(confirmBtn.title).to.equal("Auto-sync is on but won't update this URL — turn it off to make this explicit.");
    });

    it('does not affect another element\'s confirm button (multi-edit scoping)', async () => {
        const container = await fixture<HTMLDivElement>(html`
            <div>
                <div class="sluggi-source-group sluggi-source-group--active">
                    <input type="text" data-sluggi-source
                        data-formengine-input-name="data[pages][1][title]"
                        data-formengine-input-initialized value="A" />
                    <button type="button" class="sluggi-source-confirm" title="Update URL path now"></button>
                </div>
                <div class="sluggi-source-group sluggi-source-group--active">
                    <input type="text" data-sluggi-source
                        data-formengine-input-name="data[pages][2][title]"
                        data-formengine-input-initialized value="B" />
                    <button type="button" class="sluggi-source-confirm" title="Update URL path now"></button>
                </div>
            </div>
        `);
        createSiblingFields(container, '1');
        const inner = document.createElement('div');
        container.appendChild(inner);
        createSiblingFields(inner, '2');

        const el1 = document.createElement('sluggi-element') as SluggiElement;
        Object.entries({
            value: '/old/a', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/old', 'parent-slug': '/new',
            'sync-feature-enabled': '', 'is-synced': '',
        }).forEach(([k, v]) => el1.setAttribute(k, v));
        container.appendChild(el1);
        const el2 = document.createElement('sluggi-element') as SluggiElement;
        Object.entries({
            value: '/parent/b', 'record-id': '2', 'page-id': '2',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'y', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/parent', 'parent-slug': '/parent',
            'sync-feature-enabled': '', 'is-synced': '',
        }).forEach(([k, v]) => el2.setAttribute(k, v));
        container.appendChild(el2);

        await el1.updateComplete;
        await el2.updateComplete;

        const buttons = container.querySelectorAll<HTMLButtonElement>('.sluggi-source-confirm');
        expect(buttons[0].disabled).to.equal(true);
        expect(buttons[1].disabled).to.equal(false);
    });

    it('setupSourceConfirmListeners is idempotent (no duplicate handlers)', async () => {
        const { el, confirmBtn } = await makeWithSourceField('1', {
            value: '/parent/child', 'record-id': '1', 'page-id': '1',
            'table-name': 'pages', 'field-name': 'slug', language: '0',
            signature: 'x', 'parent-page-id': '1', command: 'edit',
            'locked-prefix': '/parent', 'parent-slug': '/parent',
            'sync-feature-enabled': '', 'is-synced': '',
        });
        let calls = 0;
        (el as unknown as { sendSlugProposal: (k: string) => void }).sendSlugProposal = () => { calls += 1; };
        (el as unknown as { hasNonEmptySourceFieldValue: () => boolean }).hasNonEmptySourceFieldValue = () => true;

        // Call setup again — should NOT add a second handler.
        (el as unknown as { setupSourceConfirmListeners: () => void }).setupSourceConfirmListeners();
        confirmBtn.click();

        expect(calls).to.equal(1);
    });
});
