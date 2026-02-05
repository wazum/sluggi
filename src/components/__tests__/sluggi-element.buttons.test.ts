import '../sluggi-element.js';
import { fixture, html, expect, oneEvent } from './helpers.js';
import type { SluggiElement } from './helpers.js';

describe('SluggiElement - Buttons', () => {
    describe('Regenerate Button', () => {
        it('visible and enabled when source fields have values', async () => {
            const input = document.createElement('input');
            input.setAttribute('data-sluggi-source', '');
            input.setAttribute('data-formengine-input-name', 'data[pages][123][title]');
            input.value = 'Test Title';
            document.body.appendChild(input);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test" record-id="123"></sluggi-element>
            `);

            const btn = el.shadowRoot!.querySelector('.sluggi-regenerate-btn') as HTMLButtonElement;
            expect(btn).to.exist;
            expect(btn.disabled).to.be.false;

            document.body.removeChild(input);
        });

        it('visible but disabled when source fields are empty', async () => {
            const input = document.createElement('input');
            input.setAttribute('data-sluggi-source', '');
            input.setAttribute('data-formengine-input-name', 'data[pages][123][title]');
            input.value = '';
            document.body.appendChild(input);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test" record-id="123"></sluggi-element>
            `);

            const btn = el.shadowRoot!.querySelector('.sluggi-regenerate-btn') as HTMLButtonElement;
            expect(btn).to.exist;
            expect(btn.disabled).to.be.true;

            document.body.removeChild(input);
        });

        it('visible and enabled when has-post-modifiers is set', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test" has-post-modifiers></sluggi-element>
            `);

            const btn = el.shadowRoot!.querySelector('.sluggi-regenerate-btn') as HTMLButtonElement;
            expect(btn).to.exist;
            expect(btn.disabled).to.be.false;
        });

        it('hidden when no source fields or post-modifiers', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test"></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-regenerate-btn')).to.not.exist;
        });

        it('visible but disabled when locked with toggle access', async () => {
            const input = document.createElement('input');
            input.setAttribute('data-sluggi-source', '');
            input.setAttribute('data-formengine-input-name', 'data[pages][123][title]');
            input.value = 'Test Title';
            document.body.appendChild(input);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test" record-id="123" is-locked lock-feature-enabled></sluggi-element>
            `);

            const btn = el.shadowRoot!.querySelector('.sluggi-regenerate-btn') as HTMLButtonElement;
            expect(btn).to.exist;
            expect(btn.disabled).to.be.true;

            document.body.removeChild(input);
        });

        it('visible but disabled when synced with toggle access', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][123][title]');
            titleInput.value = 'Test Title';
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test" record-id="123" is-synced sync-feature-enabled></sluggi-element>
            `);

            const btn = el.shadowRoot!.querySelector('.sluggi-regenerate-btn') as HTMLButtonElement;
            expect(btn).to.exist;
            expect(btn.disabled).to.be.true;

            document.body.removeChild(titleInput);
        });

        it('becomes disabled when source field is cleared via change event', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][123][title]');
            titleInput.value = 'Some Title';
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test" record-id="123"></sluggi-element>
            `);
            const btn = el.shadowRoot!.querySelector('.sluggi-regenerate-btn') as HTMLButtonElement;
            expect(btn).to.exist;
            expect(btn.disabled).to.be.false;

            titleInput.value = '';
            titleInput.dispatchEvent(new Event('change', { bubbles: true }));
            await new Promise(r => setTimeout(r, 200));
            await el.updateComplete;

            expect(btn.disabled).to.be.true;

            document.body.removeChild(titleInput);
        });

        it('dispatches sluggi-request-proposal with mode recreate when clicked', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
            titleInput.value = 'Test Title';
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/old-slug"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                    language="0"
                    signature="test-sig"
                    command="edit"
                    parent-page-id="1"
                ></sluggi-element>
            `);

            const btn = el.shadowRoot!.querySelector('.sluggi-regenerate-btn') as HTMLElement;
            setTimeout(() => btn.click());

            const event = await oneEvent(el, 'sluggi-request-proposal') as CustomEvent;
            expect(event.detail.mode).to.equal('recreate');
            expect(event.detail.pageId).to.equal('123');
            expect(event.detail.recordId).to.equal('456');
            expect(event.detail.tableName).to.equal('pages');
            expect(event.detail.fieldName).to.equal('slug');
            expect(event.detail.language).to.equal('0');
            expect(event.detail.signature).to.equal('test-sig');
            expect(event.detail.command).to.equal('edit');
            expect(event.detail.parentPageId).to.equal('1');
            expect(event.detail.currentValue).to.equal('/old-slug');

            document.body.removeChild(titleInput);
        });
    });

    describe('Copy URL Button', () => {
        it('is hidden when copyUrlFeatureEnabled is false', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/demo"></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-copy-url-btn')).to.be.null;
        });

        it('is visible when copyUrlFeatureEnabled is true', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo"
                    copy-url-feature-enabled
                    page-url="https://example.com"
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-copy-url-btn')).to.exist;
        });

        it('copies full URL to clipboard when clicked', async () => {
            let copiedText = '';
            Object.defineProperty(navigator, 'clipboard', {
                value: { writeText: async (text: string) => { copiedText = text; } },
                writable: true,
                configurable: true
            });

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo-page"
                    copy-url-feature-enabled
                    page-url="https://example.com"
                ></sluggi-element>
            `);

            const copyBtn = el.shadowRoot!.querySelector('.sluggi-copy-url-btn') as HTMLButtonElement;
            copyBtn.click();
            await el.updateComplete;

            expect(copiedText).to.equal('https://example.com/demo-page');
        });

        it('handles trailing slash in page-url correctly', async () => {
            let copiedText = '';
            Object.defineProperty(navigator, 'clipboard', {
                value: { writeText: async (text: string) => { copiedText = text; } },
                writable: true,
                configurable: true
            });

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo-page"
                    copy-url-feature-enabled
                    page-url="https://example.com/"
                ></sluggi-element>
            `);

            const copyBtn = el.shadowRoot!.querySelector('.sluggi-copy-url-btn') as HTMLButtonElement;
            copyBtn.click();
            await el.updateComplete;

            expect(copiedText).to.equal('https://example.com/demo-page');
        });

        it('shows checkmark icon and confirmation message after copying', async () => {
            Object.defineProperty(navigator, 'clipboard', {
                value: { writeText: async () => {} },
                writable: true,
                configurable: true
            });

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo-page"
                    copy-url-feature-enabled
                    page-url="https://example.com"
                ></sluggi-element>
            `);

            const copyBtn = el.shadowRoot!.querySelector('.sluggi-copy-url-btn') as HTMLButtonElement;
            copyBtn.click();
            await el.updateComplete;

            expect(copyBtn.classList.contains('is-copied')).to.be.true;

            const note = el.shadowRoot!.querySelector('.sluggi-copy-confirmation');
            expect(note).to.exist;
        });

        it('shows open-in-new-tab link with correct URL after copying', async () => {
            Object.defineProperty(navigator, 'clipboard', {
                value: { writeText: async () => {} },
                writable: true,
                configurable: true
            });

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo-page"
                    copy-url-feature-enabled
                    page-url="https://example.com"
                ></sluggi-element>
            `);

            const copyBtn = el.shadowRoot!.querySelector('.sluggi-copy-url-btn') as HTMLButtonElement;
            copyBtn.click();
            await el.updateComplete;

            const link = el.shadowRoot!.querySelector('.sluggi-copy-confirmation a') as HTMLAnchorElement;
            expect(link).to.exist;
            expect(link.href).to.equal('https://example.com/demo-page');
            expect(link.target).to.equal('_blank');
            expect(link.rel).to.equal('noopener noreferrer');
        });

        it('reverts to original icon after timeout', async () => {
            Object.defineProperty(navigator, 'clipboard', {
                value: { writeText: async () => {} },
                writable: true,
                configurable: true
            });

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo-page"
                    copy-url-feature-enabled
                    page-url="https://example.com"
                ></sluggi-element>
            `);

            const copyBtn = el.shadowRoot!.querySelector('.sluggi-copy-url-btn') as HTMLButtonElement;
            copyBtn.click();
            await el.updateComplete;

            expect(copyBtn.classList.contains('is-copied')).to.be.true;

            await new Promise(resolve => setTimeout(resolve, 4100));
            await el.updateComplete;

            expect(copyBtn.classList.contains('is-copied')).to.be.false;
            expect(el.shadowRoot!.querySelector('.sluggi-copy-confirmation')).to.be.null;
        });
    });

    describe('Full Path Edit Button', () => {
        it('is visible next to edit button when restrictions apply', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent/child"
                    last-segment-only
                    full-path-feature-enabled
                ></sluggi-element>
            `);

            const editBtn = el.shadowRoot!.querySelector('.sluggi-edit-btn');
            const fullPathEditBtn = el.shadowRoot!.querySelector('.sluggi-full-path-edit-btn');
            expect(editBtn).to.exist;
            expect(fullPathEditBtn).to.exist;
        });

        it('enters edit mode with full path enabled when clicked', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent/child"
                    last-segment-only
                    full-path-feature-enabled
                ></sluggi-element>
            `);

            const fullPathEditBtn = el.shadowRoot!.querySelector('.sluggi-full-path-edit-btn') as HTMLElement;
            fullPathEditBtn.click();
            await el.updateComplete;

            const input = el.shadowRoot!.querySelector('input.sluggi-input');
            expect(input).to.exist;

            const prefix = el.shadowRoot!.querySelector('.sluggi-prefix');
            expect(prefix).to.be.null;
        });

        it('is disabled when slug is locked', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent/child"
                    last-segment-only
                    full-path-feature-enabled
                    lock-feature-enabled
                    is-locked
                ></sluggi-element>
            `);

            const fullPathEditBtn = el.shadowRoot!.querySelector('.sluggi-full-path-edit-btn') as HTMLButtonElement;
            expect(fullPathEditBtn).to.exist;
            expect(fullPathEditBtn.disabled).to.be.true;
        });

        it('is disabled when slug is synced', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
            titleInput.value = 'Demo';
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent/child"
                    record-id="456"
                    last-segment-only
                    full-path-feature-enabled
                    sync-feature-enabled
                    is-synced
                ></sluggi-element>
            `);

            const fullPathEditBtn = el.shadowRoot!.querySelector('.sluggi-full-path-edit-btn') as HTMLButtonElement;
            expect(fullPathEditBtn).to.exist;
            expect(fullPathEditBtn.disabled).to.be.true;

            document.body.removeChild(titleInput);
        });

        it('sets full-path field to 1 when confirming full path edit with changes', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <sluggi-element
                    value="/parent/original"
                    prefix="/parent"
                    full-path-feature-enabled
                    last-segment-only
                ></sluggi-element>
                <input type="hidden" class="sluggi-hidden-field" value="/parent/original" />
                <input type="hidden" class="sluggi-full-path-field" value="0" />
            `;
            document.body.appendChild(container);

            const el = container.querySelector('sluggi-element') as SluggiElement;
            const fullPathField = container.querySelector('.sluggi-full-path-field') as HTMLInputElement;
            await el.updateComplete;

            expect(fullPathField.value).to.equal('0');

            const fullPathEditBtn = el.shadowRoot!.querySelector('.sluggi-full-path-edit-btn') as HTMLButtonElement;
            fullPathEditBtn.click();
            await el.updateComplete;

            expect(fullPathField.value).to.equal('1');

            const input = el.shadowRoot!.querySelector('input.sluggi-input') as HTMLInputElement;
            input.value = 'new-full-path';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(fullPathField.value, 'Full path field should remain 1 after confirming edit').to.equal('1');

            document.body.removeChild(container);
        });

        it('does NOT prepend prefix when confirming full path edit', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <sluggi-element
                    value="/parent/original"
                    prefix="/parent"
                    full-path-feature-enabled
                    last-segment-only
                ></sluggi-element>
                <input type="hidden" class="sluggi-hidden-field" value="/parent/original" />
                <input type="hidden" class="sluggi-full-path-field" value="0" />
            `;
            document.body.appendChild(container);

            const el = container.querySelector('sluggi-element') as SluggiElement;
            const hiddenField = container.querySelector('.sluggi-hidden-field') as HTMLInputElement;
            await el.updateComplete;

            const fullPathEditBtn = el.shadowRoot!.querySelector('.sluggi-full-path-edit-btn') as HTMLButtonElement;
            fullPathEditBtn.click();
            await el.updateComplete;

            const input = el.shadowRoot!.querySelector('input.sluggi-input') as HTMLInputElement;
            input.value = 'new-root/new-segment';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(hiddenField.value, 'Slug should be the full path without prefix prepended').to.equal('/new-root/new-segment');

            document.body.removeChild(container);
        });
    });
});
