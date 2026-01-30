import '../sluggi-element.js';
import { fixture, html, expect, oneEvent } from './helpers.js';
import type { SluggiElement } from './helpers.js';

describe('SluggiElement - Source Field Listening', () => {
    it('finds and caches source field elements via data-sluggi-source', async () => {
        const titleInput = document.createElement('input');
        titleInput.setAttribute('data-sluggi-source', '');
        titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
        titleInput.value = 'Test Title';
        document.body.appendChild(titleInput);

        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                table-name="pages"
                record-id="456"
            ></sluggi-element>
        `);

        const values = (el as any).collectFormFieldValues();
        expect(values).to.deep.equal({ title: 'Test Title' });

        document.body.removeChild(titleInput);
    });

    it('only uses source fields matching its own record ID in multi-edit mode', async () => {
        const title1 = document.createElement('input');
        title1.setAttribute('data-sluggi-source', '');
        title1.setAttribute('data-formengine-input-name', 'data[pages][100][title]');
        title1.value = 'Page One Title';
        document.body.appendChild(title1);

        const title2 = document.createElement('input');
        title2.setAttribute('data-sluggi-source', '');
        title2.setAttribute('data-formengine-input-name', 'data[pages][200][title]');
        title2.value = 'Page Two Title';
        document.body.appendChild(title2);

        const title3 = document.createElement('input');
        title3.setAttribute('data-sluggi-source', '');
        title3.setAttribute('data-formengine-input-name', 'data[pages][300][title]');
        title3.value = 'Page Three Title';
        document.body.appendChild(title3);

        const el1 = document.createElement('sluggi-element') as SluggiElement;
        el1.setAttribute('value', '/page-one');
        el1.setAttribute('table-name', 'pages');
        el1.setAttribute('record-id', '100');
        document.body.appendChild(el1);

        const el2 = document.createElement('sluggi-element') as SluggiElement;
        el2.setAttribute('value', '/page-two');
        el2.setAttribute('table-name', 'pages');
        el2.setAttribute('record-id', '200');
        document.body.appendChild(el2);

        const el3 = document.createElement('sluggi-element') as SluggiElement;
        el3.setAttribute('value', '/page-three');
        el3.setAttribute('table-name', 'pages');
        el3.setAttribute('record-id', '300');
        document.body.appendChild(el3);

        await el1.updateComplete;
        await el2.updateComplete;
        await el3.updateComplete;

        const values1 = (el1 as any).collectFormFieldValues();
        const values2 = (el2 as any).collectFormFieldValues();
        const values3 = (el3 as any).collectFormFieldValues();

        expect(values1, 'Element 1 should only see Page One Title').to.deep.equal({ title: 'Page One Title' });
        expect(values2, 'Element 2 should only see Page Two Title').to.deep.equal({ title: 'Page Two Title' });
        expect(values3, 'Element 3 should only see Page Three Title').to.deep.equal({ title: 'Page Three Title' });

        document.body.removeChild(el1);
        document.body.removeChild(el2);
        document.body.removeChild(el3);
        document.body.removeChild(title1);
        document.body.removeChild(title2);
        document.body.removeChild(title3);
    });

    it('collects values from multiple source fields', async () => {
        const titleInput = document.createElement('input');
        titleInput.setAttribute('data-sluggi-source', '');
        titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
        titleInput.value = 'Page Title';
        document.body.appendChild(titleInput);

        const navTitleInput = document.createElement('input');
        navTitleInput.setAttribute('data-sluggi-source', '');
        navTitleInput.setAttribute('data-formengine-input-name', 'data[pages][456][nav_title]');
        navTitleInput.value = 'Nav Title';
        document.body.appendChild(navTitleInput);

        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                table-name="pages"
                record-id="456"
            ></sluggi-element>
        `);

        const values = (el as any).collectFormFieldValues();
        expect(values).to.deep.equal({ title: 'Page Title', nav_title: 'Nav Title' });

        document.body.removeChild(titleInput);
        document.body.removeChild(navTitleInput);
    });

    it('triggers recreate mode on source field change when isSynced', async () => {
        const titleInput = document.createElement('input');
        titleInput.setAttribute('data-sluggi-source', '');
        titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
        titleInput.value = 'Initial Title';
        document.body.appendChild(titleInput);

        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                table-name="pages"
                record-id="456"
                sync-feature-enabled
                is-synced
            ></sluggi-element>
        `);

        titleInput.value = 'New Title';

        setTimeout(() => titleInput.dispatchEvent(new Event('change')));

        const event = await oneEvent(el, 'sluggi-request-proposal') as CustomEvent;
        expect(event.detail.mode).to.equal('recreate');

        document.body.removeChild(titleInput);
    });

    it('does not trigger auto-sync when not isSynced and not new', async () => {
        const titleInput = document.createElement('input');
        titleInput.setAttribute('data-sluggi-source', '');
        titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
        titleInput.value = 'Initial Title';
        document.body.appendChild(titleInput);

        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                table-name="pages"
                record-id="456"
                command="edit"
            ></sluggi-element>
        `);

        let eventFired = false;
        el.addEventListener('sluggi-request-proposal', () => { eventFired = true; });

        titleInput.value = 'New Title';
        titleInput.dispatchEvent(new Event('change'));

        await el.updateComplete;
        expect(eventFired).to.be.false;

        document.body.removeChild(titleInput);
    });

    describe('empty source field handling', () => {
        it('does NOT trigger proposal when enabling sync with empty fields', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
            titleInput.value = '';
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/existing-slug"
                    table-name="pages"
                    record-id="456"
                    sync-feature-enabled
                ></sluggi-element>
            `);

            let eventFired = false;
            el.addEventListener('sluggi-request-proposal', () => { eventFired = true; });

            const syncToggle = el.shadowRoot!.querySelector('.sluggi-sync-toggle') as HTMLElement;
            syncToggle.click();

            await el.updateComplete;
            expect(eventFired).to.be.false;

            document.body.removeChild(titleInput);
        });

        it('does NOT trigger auto-sync when changed field value is empty', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
            titleInput.value = 'Initial Title';
            document.body.appendChild(titleInput);

            const navTitleInput = document.createElement('input');
            navTitleInput.setAttribute('data-sluggi-source', '');
            navTitleInput.setAttribute('data-formengine-input-name', 'data[pages][456][nav_title]');
            navTitleInput.value = 'Nav Title';
            document.body.appendChild(navTitleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/initial-title"
                    table-name="pages"
                    record-id="456"
                    sync-feature-enabled
                    is-synced
                ></sluggi-element>
            `);

            let eventFired = false;
            el.addEventListener('sluggi-request-proposal', () => { eventFired = true; });

            titleInput.value = '';
            titleInput.dispatchEvent(new Event('change'));

            await el.updateComplete;
            expect(eventFired).to.be.false;

            document.body.removeChild(titleInput);
            document.body.removeChild(navTitleInput);
        });

        it('does NOT trigger auto-sync when required source field is empty', async () => {
            const customField = document.createElement('input');
            customField.setAttribute('data-sluggi-source', '');
            customField.setAttribute('data-formengine-input-name', 'data[tx_myext_record][789][my_required_field]');
            customField.value = '';
            document.body.appendChild(customField);

            const otherField = document.createElement('input');
            otherField.setAttribute('data-sluggi-source', '');
            otherField.setAttribute('data-formengine-input-name', 'data[tx_myext_record][789][other_field]');
            otherField.value = 'Some Value';
            document.body.appendChild(otherField);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/existing-slug"
                    table-name="tx_myext_record"
                    record-id="789"
                    sync-feature-enabled
                    is-synced
                    required-source-fields="my_required_field"
                ></sluggi-element>
            `);

            let eventFired = false;
            el.addEventListener('sluggi-request-proposal', () => { eventFired = true; });

            otherField.value = 'Updated Value';
            otherField.dispatchEvent(new Event('change'));

            await el.updateComplete;
            expect(eventFired).to.be.false;

            document.body.removeChild(customField);
            document.body.removeChild(otherField);
        });
    });
});
