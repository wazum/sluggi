import '../sluggi-element.js';
import { fixture, html, expect, enterEditMode } from './helpers.js';
import type { SluggiElement } from './helpers.js';

describe('SluggiElement - Core', () => {
    describe('Rendering', () => {
        it('displays slug parts correctly', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    prefix="/parent"
                    value="/my-page"
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-prefix')?.textContent).to.contain('/parent');
            expect(el.shadowRoot!.querySelector('.sluggi-editable')?.textContent).to.contain('/my-page');
        });

        it('shows loading spinner when loading', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test" loading></sluggi-element>
            `);
            expect(el.shadowRoot!.querySelector('.sluggi-spinner')).to.exist;
        });

        it('shows placeholder when source field is empty on new page', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <input data-sluggi-source data-formengine-input-name="data[pages][123][title]" value="" />
                <sluggi-element value="/parent" locked-prefix="/parent" command="new" record-id="123"></sluggi-element>
            `;
            document.body.appendChild(container);
            const el = container.querySelector('sluggi-element') as SluggiElement;
            await el.updateComplete;

            const editable = el.shadowRoot!.querySelector('.sluggi-editable');
            expect(editable?.textContent).to.equal('/new-page');
            expect(el.shadowRoot!.querySelector('.sluggi-placeholder')).to.exist;
            document.body.removeChild(container);
        });

        it('shows "/" between parent slug and placeholder for new page (no locked-prefix)', async () => {
            const container = document.createElement('div');
            const input = document.createElement('input');
            input.dataset.sluggiSource = '';
            input.setAttribute('data-formengine-input-name', 'data[pages][123][title]');
            input.value = '';
            const sluggi = document.createElement('sluggi-element') as SluggiElement;
            sluggi.setAttribute('value', '/test-page');
            sluggi.setAttribute('command', 'new');
            sluggi.setAttribute('record-id', '123');
            container.append(input, sluggi);
            document.body.appendChild(container);
            await sluggi.updateComplete;

            const editable = sluggi.shadowRoot!.querySelector('.sluggi-editable');
            // Expect `/test-page/new-page` — the "/" between the parent path and
            // the placeholder belongs to the URL structure, not the placeholder label.
            expect(editable?.textContent).to.equal('/test-page/new-page');
            expect(sluggi.shadowRoot!.querySelector('.sluggi-placeholder')).to.exist;
            document.body.removeChild(container);
        });

        it('omits "/" before placeholder for non-page records', async () => {
            const container = document.createElement('div');
            const input = document.createElement('input');
            input.dataset.sluggiSource = '';
            input.setAttribute('data-formengine-input-name', 'data[tx_news_domain_model_news][456][title]');
            input.value = '';
            const sluggi = document.createElement('sluggi-element') as SluggiElement;
            sluggi.setAttribute('value', '');
            sluggi.setAttribute('command', 'new');
            sluggi.setAttribute('record-id', '456');
            sluggi.setAttribute('table-name', 'tx_news_domain_model_news');
            sluggi.setAttribute('field-name', 'path_segment');
            container.append(input, sluggi);
            document.body.appendChild(container);
            await sluggi.updateComplete;

            const editable = sluggi.shadowRoot!.querySelector('.sluggi-editable');
            // Non-page records don't follow the parent-hierarchy URL pattern, so
            // the placeholder should not get a leading "/".
            expect(editable?.textContent).to.equal('new-record');
            expect(sluggi.shadowRoot!.querySelector('.sluggi-placeholder')).to.exist;
            document.body.removeChild(container);
        });

        it('does not show placeholder on existing page even when source field is empty', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <input data-sluggi-source data-formengine-input-name="data[pages][123][title]" value="" />
                <sluggi-element value="/parent/child" locked-prefix="/parent" command="edit"></sluggi-element>
            `;
            document.body.appendChild(container);
            const el = container.querySelector('sluggi-element') as SluggiElement;
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-placeholder')).to.not.exist;
            document.body.removeChild(container);
        });

        it('does not show placeholder when source field has value', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <input data-sluggi-source data-formengine-input-name="data[pages][123][title]" value="My Page Title" />
                <sluggi-element value="/my-page" prefix="/parent"></sluggi-element>
            `;
            document.body.appendChild(container);
            const el = container.querySelector('sluggi-element') as SluggiElement;
            await el.updateComplete;

            const editable = el.shadowRoot!.querySelector('.sluggi-editable');
            expect(editable?.textContent).to.contain('/my-page');
            expect(el.shadowRoot!.querySelector('.sluggi-placeholder')).to.not.exist;
            document.body.removeChild(container);
        });

        it('does not show placeholder when editing existing page with title', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <input data-sluggi-source data-formengine-input-name="data[pages][26][title]" value="About Us" />
                <sluggi-element
                    value="/organization/department/institute/about-page"
                    locked-prefix="/organization/department"
                    command="edit"
                ></sluggi-element>
            `;
            document.body.appendChild(container);
            const el = container.querySelector('sluggi-element') as SluggiElement;
            await el.updateComplete;

            const editable = el.shadowRoot!.querySelector('.sluggi-editable');
            expect(editable?.textContent).to.contain('/institute/about-page');
            expect(el.shadowRoot!.querySelector('.sluggi-placeholder'), 'placeholder should not exist').to.not.exist;
            document.body.removeChild(container);
        });

        it('should keep placeholder while typing until sync happens on new page', async () => {
            const sourceInput = document.createElement('input');
            sourceInput.setAttribute('data-sluggi-source', '');
            sourceInput.setAttribute('data-formengine-input-name', 'data[pages][NEW123][title]');
            sourceInput.value = '';
            document.body.appendChild(sourceInput);

            const el = document.createElement('sluggi-element') as SluggiElement;
            el.setAttribute('value', '/parent/child');
            el.setAttribute('locked-prefix', '/parent');
            el.setAttribute('command', 'new');
            el.setAttribute('record-id', 'NEW123');
            document.body.appendChild(el);

            await el.updateComplete;
            expect(el.shadowRoot!.querySelector('.sluggi-placeholder')).to.exist;

            sourceInput.value = 'My New Page';
            sourceInput.dispatchEvent(new Event('input', { bubbles: true }));
            await new Promise(r => setTimeout(r, 200));
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-placeholder'), 'placeholder should stay while typing').to.exist;

            el.setProposal('/parent/child/my-new-page');
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-placeholder'), 'placeholder should hide after sync').to.not.exist;

            document.body.removeChild(el);
            document.body.removeChild(sourceInput);
        });

        it('shows "new-record" placeholder for new non-page table records', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value=""
                    table-name="tx_news_domain_model_news"
                    command="new"
                ></sluggi-element>
            `);

            (el as any).hasSourceFields = true;
            await el.updateComplete;

            const placeholder = el.shadowRoot!.querySelector('.sluggi-placeholder');
            expect(placeholder, 'non-page tables should show placeholder').to.exist;
            expect(placeholder!.textContent).to.equal('new-record');

            const editable = el.shadowRoot!.querySelector('.sluggi-editable-end');
            expect(editable?.textContent?.trim() || '', 'should not show / before placeholder for non-page tables').to.equal('');
        });

    });

    describe('Properties', () => {
        const attributeTestCases = [
            { attribute: 'page-id', property: 'pageId', value: '123' },
            { attribute: 'record-id', property: 'recordId', value: '456' },
            { attribute: 'table-name', property: 'tableName', value: 'pages' },
            { attribute: 'field-name', property: 'fieldName', value: 'slug' },
            { attribute: 'language', property: 'language', value: '0' },
            { attribute: 'signature', property: 'signature', value: 'abc123' },
            { attribute: 'command', property: 'command', value: 'edit' },
            { attribute: 'parent-page-id', property: 'parentPageId', value: '1' },
        ];

        attributeTestCases.forEach(({ attribute, property, value }) => {
            it(`accepts ${attribute} attribute`, async () => {
                const container = document.createElement('div');
                container.innerHTML = `<sluggi-element value="/test" ${attribute}="${value}"></sluggi-element>`;
                document.body.appendChild(container);
                const el = container.querySelector('sluggi-element') as SluggiElement;
                await el.updateComplete;
                expect((el as any)[property]).to.equal(value);
                document.body.removeChild(container);
            });
        });

        it('has sendSlugProposal method', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test"></sluggi-element>
            `);
            expect(el.sendSlugProposal).to.be.a('function');
        });
    });

    describe('Core Editing', () => {
        it('enters edit mode and focuses input on click', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test"></sluggi-element>
            `);

            const input = await enterEditMode(el);

            expect(input).to.exist;
            expect(el.shadowRoot!.activeElement).to.equal(input);
        });

        it('saves value on Enter and exits edit mode', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/old-slug"></sluggi-element>
            `);

            const input = await enterEditMode(el);
            input.value = 'new-slug';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/new-slug');
            expect(el.shadowRoot!.querySelector('input.sluggi-input')).to.not.exist;
        });

        it('cancels edit on Escape without saving', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/original"></sluggi-element>
            `);

            const input = await enterEditMode(el);
            input.value = '/changed';
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/original');
            expect(el.shadowRoot!.querySelector('input.sluggi-input')).to.not.exist;
        });

        it('does not show slash prefix in edit mode for non-page tables', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="my-news-slug"
                    table-name="tx_news_domain_model_news"
                ></sluggi-element>
            `);

            await enterEditMode(el);
            const hasSlashPrefix = el.shadowRoot!.querySelector('.sluggi-input-prefix') !== null;
            expect(hasSlashPrefix, 'non-page tables should not show / prefix in edit mode').to.be.false;
        });

        it('prefix remains unchanged when editing value', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element prefix="/inaccessible" value="/editable"></sluggi-element>
            `);

            const input = await enterEditMode(el);
            input.value = 'new-value';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-prefix')?.textContent).to.contain('/inaccessible');
            expect(el.value).to.equal('/new-value');
        });
    });
});
