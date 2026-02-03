import '../sluggi-element.js';
import { fixture, html, expect, oneEvent } from './helpers.js';
import type { SluggiElement } from './helpers.js';

describe('SluggiElement - Toggles', () => {
    describe('Sync Toggle', () => {
        it('uses recreate mode when enabling sync', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
            titleInput.value = 'Demo';
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo-1"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                    sync-feature-enabled
                ></sluggi-element>
            `);

            const syncToggle = el.shadowRoot!.querySelector('.sluggi-sync-toggle') as HTMLElement;
            expect(syncToggle).to.exist;

            setTimeout(() => syncToggle.click());

            const event = await oneEvent(el, 'sluggi-request-proposal') as CustomEvent;
            expect(event.detail.mode).to.equal('recreate');

            document.body.removeChild(titleInput);
        });

        it('is hidden when syncFeatureEnabled is false', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
            titleInput.value = 'Demo';
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo-1"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-sync-toggle')).to.be.null;

            document.body.removeChild(titleInput);
        });

        it('is visible when syncFeatureEnabled is true but isSynced is false (allows re-enabling)', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
            titleInput.value = 'Demo';
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo-1"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                    sync-feature-enabled
                ></sluggi-element>
            `);

            expect(el.isSynced).to.be.false;
            expect(el.shadowRoot!.querySelector('.sluggi-sync-toggle')).to.exist;

            document.body.removeChild(titleInput);
        });

        it('is hidden when no source fields exist even if syncFeatureEnabled is true', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo-1"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                    sync-feature-enabled
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-sync-toggle')).to.be.null;
        });

        it('is disabled when isLocked is true (lock takes precedence)', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
            titleInput.value = 'Demo';
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo-1"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                    sync-feature-enabled
                    lock-feature-enabled
                    is-locked
                ></sluggi-element>
            `);

            const syncToggle = el.shadowRoot!.querySelector('.sluggi-sync-toggle') as HTMLButtonElement;
            expect(syncToggle).to.exist;
            expect(syncToggle.disabled).to.be.true;

            document.body.removeChild(titleInput);
        });

        it('hides source badges when toggling sync OFF', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
            titleInput.value = 'Demo';
            document.body.appendChild(titleInput);

            const wrapper = document.createElement('div');
            wrapper.className = 'input-group';
            const badge = document.createElement('span');
            badge.className = 'sluggi-source-badge';
            wrapper.appendChild(badge);
            document.body.appendChild(wrapper);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo-1"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                    sync-feature-enabled
                    is-synced
                ></sluggi-element>
            `);

            expect(badge.style.display).to.equal('');
            expect(wrapper.classList.contains('input-group')).to.be.true;

            const syncToggle = el.shadowRoot!.querySelector('.sluggi-sync-toggle') as HTMLElement;
            syncToggle.click();
            await el.updateComplete;

            expect(badge.style.display).to.equal('none');
            expect(wrapper.classList.contains('input-group')).to.be.false;

            document.body.removeChild(titleInput);
            document.body.removeChild(wrapper);
        });

        it('shows source badges when toggling sync ON', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
            titleInput.value = 'Demo';
            document.body.appendChild(titleInput);

            const wrapper = document.createElement('div');
            const badge = document.createElement('span');
            badge.className = 'sluggi-source-badge';
            badge.style.display = 'none';
            wrapper.appendChild(badge);
            document.body.appendChild(wrapper);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo-1"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                    sync-feature-enabled
                ></sluggi-element>
            `);

            expect(badge.style.display).to.equal('none');
            expect(wrapper.classList.contains('input-group')).to.be.false;

            const syncToggle = el.shadowRoot!.querySelector('.sluggi-sync-toggle') as HTMLElement;
            syncToggle.click();
            await el.updateComplete;

            expect(badge.style.display).to.equal('');
            expect(wrapper.classList.contains('input-group')).to.be.true;

            document.body.removeChild(titleInput);
            document.body.removeChild(wrapper);
        });

        it('should NOT auto-sync new pages when sync is toggled OFF', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][NEW123][title]');
            titleInput.value = 'Initial';
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value=""
                    page-id="123"
                    record-id="NEW123"
                    table-name="pages"
                    field-name="slug"
                    command="new"
                    sync-feature-enabled
                ></sluggi-element>
            `);

            expect(el.isSynced).to.be.false;

            let proposalRequested = false;
            el.addEventListener('sluggi-request-proposal', () => {
                proposalRequested = true;
            });

            titleInput.value = 'Changed Title';
            titleInput.dispatchEvent(new Event('change', { bubbles: true }));
            await el.updateComplete;
            await new Promise(r => setTimeout(r, 50));

            expect(proposalRequested).to.be.false;

            document.body.removeChild(titleInput);
        });

        it('reverts to pre-sync value when toggling sync OFF without saving', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
            titleInput.value = 'Some Title';
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/my-custom-slug"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                    sync-feature-enabled
                ></sluggi-element>
            `);

            expect(el.value).to.equal('/my-custom-slug');

            const syncToggle = el.shadowRoot!.querySelector('.sluggi-sync-toggle') as HTMLElement;
            syncToggle.click();
            await el.updateComplete;

            expect(el.isSynced).to.be.true;

            el.setProposal('/some-title');
            await el.updateComplete;
            expect(el.value).to.equal('/some-title');

            syncToggle.click();
            await el.updateComplete;

            expect(el.isSynced).to.be.false;
            expect(el.value).to.equal('/my-custom-slug');

            document.body.removeChild(titleInput);
        });

        it('enabling SYNC disables full path edit button', async () => {
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
                    sync-feature-enabled
                    full-path-feature-enabled
                ></sluggi-element>
            `);

            const fullPathEditBtn = el.shadowRoot!.querySelector('.sluggi-full-path-edit-btn') as HTMLButtonElement;
            const syncToggle = el.shadowRoot!.querySelector('.sluggi-sync-toggle') as HTMLElement;

            expect(fullPathEditBtn).to.exist;
            expect(syncToggle).to.exist;
            expect(fullPathEditBtn.disabled).to.be.false;

            syncToggle.click();
            await el.updateComplete;

            expect(syncToggle.classList.contains('is-synced')).to.be.true;
            expect(fullPathEditBtn.disabled).to.be.true;

            document.body.removeChild(titleInput);
        });
    });

    describe('Lock Toggle', () => {
        it('is hidden when lockFeatureEnabled is false', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-lock-toggle')).to.be.null;
        });

        it('is visible when lockFeatureEnabled is true', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                    lock-feature-enabled
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-lock-toggle')).to.exist;
        });

        it('has is-locked class when isLocked is true', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                    lock-feature-enabled
                    is-locked
                ></sluggi-element>
            `);

            const lockToggle = el.shadowRoot!.querySelector('.sluggi-lock-toggle');
            expect(lockToggle?.classList.contains('is-locked')).to.be.true;
        });

        it('toggles lock state when clicked', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                    lock-feature-enabled
                ></sluggi-element>
            `);

            expect(el.isLocked).to.be.false;

            const lockToggle = el.shadowRoot!.querySelector('.sluggi-lock-toggle') as HTMLElement;
            lockToggle.click();
            await el.updateComplete;

            expect(el.isLocked).to.be.true;
            const updatedLockToggle = el.shadowRoot!.querySelector('.sluggi-lock-toggle');
            expect(updatedLockToggle?.classList.contains('is-locked')).to.be.true;
        });

        it('is disabled when isSynced is true', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
            titleInput.value = 'Demo';
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/demo"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                    lock-feature-enabled
                    sync-feature-enabled
                    is-synced
                ></sluggi-element>
            `);

            const lockToggle = el.shadowRoot!.querySelector('.sluggi-lock-toggle') as HTMLButtonElement;
            expect(lockToggle).to.exist;
            expect(lockToggle.disabled).to.be.true;

            document.body.removeChild(titleInput);
        });

        it('updates hidden lock field when toggled', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <sluggi-element
                    value="/demo"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                    lock-feature-enabled
                ></sluggi-element>
                <input type="hidden" class="sluggi-lock-field" value="0" />
            `;
            document.body.appendChild(container);

            const el = container.querySelector('sluggi-element') as SluggiElement;
            const lockField = container.querySelector('.sluggi-lock-field') as HTMLInputElement;
            await el.updateComplete;

            const lockToggle = el.shadowRoot!.querySelector('.sluggi-lock-toggle') as HTMLElement;
            lockToggle.click();
            await el.updateComplete;

            expect(lockField.value).to.equal('1');

            document.body.removeChild(container);
        });

        it('prevents editing when locked', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    is-locked
                ></sluggi-element>
            `);

            const editable = el.shadowRoot!.querySelector('.sluggi-editable') as HTMLElement;
            editable?.click();
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('input.sluggi-input')).to.not.exist;
        });

        it('enabling LOCK disables full path edit button', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent/child"
                    last-segment-only
                    lock-feature-enabled
                    full-path-feature-enabled
                ></sluggi-element>
            `);

            const fullPathEditBtn = el.shadowRoot!.querySelector('.sluggi-full-path-edit-btn') as HTMLButtonElement;
            const lockToggle = el.shadowRoot!.querySelector('.sluggi-lock-toggle') as HTMLElement;

            expect(fullPathEditBtn).to.exist;
            expect(lockToggle).to.exist;
            expect(fullPathEditBtn.disabled).to.be.false;

            lockToggle.click();
            await el.updateComplete;

            expect(lockToggle.classList.contains('is-locked')).to.be.true;
            expect(fullPathEditBtn.disabled).to.be.true;
        });
    });
});
