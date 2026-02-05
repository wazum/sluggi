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
