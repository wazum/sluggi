import '../sluggi-element.js';
import { fixture, html, expect, oneEvent, enterEditMode } from './helpers.js';
import type { SluggiElement } from './helpers.js';

describe('SluggiElement - Integration', () => {
    describe('Conflict Handling', () => {
        it('sets conflict state with conflicting slug and proposal', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/current"></sluggi-element>
            `);

            el.setProposal('/suggested-1', true, '/conflicting');
            await el.updateComplete;

            expect(el.hasConflict).to.be.true;
            expect(el.conflictProposal).to.equal('/suggested-1');
        });

        it('stores attempted slug after save for conflict detection', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/original"></sluggi-element>
            `);

            const inputEl = await enterEditMode(el);
            inputEl.value = 'demo';
            inputEl.dispatchEvent(new Event('input', { bubbles: true }));
            inputEl.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            el.setProposal('/demo-1', true);
            await el.updateComplete;

            expect(el.hasConflict).to.be.true;
            expect(el.conflictProposal).to.equal('/demo-1');
        });

        it('stores conflicting slug on blur for later conflict detection', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/original"></sluggi-element>
            `);

            const inputEl = await enterEditMode(el);
            inputEl.value = 'demo';
            inputEl.dispatchEvent(new Event('input', { bubbles: true }));
            inputEl.dispatchEvent(new FocusEvent('blur', { bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/demo');

            el.setProposal('/demo-1', true, '/demo');
            await el.updateComplete;

            expect(el.hasConflict).to.be.true;
        });

        it('setProposal updates value or sets conflict state', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test"></sluggi-element>
            `);

            el.setProposal('/accepted');
            await el.updateComplete;
            expect(el.value).to.equal('/accepted');
            expect(el.hasConflict).to.be.false;

            el.setProposal('/conflicting', true);
            await el.updateComplete;
            expect(el.hasConflict).to.be.true;
            expect(el.conflictProposal).to.equal('/conflicting');
        });
    });

    describe('Events', () => {
        it('dispatches sluggi-change with new value on save', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/old"></sluggi-element>
            `);

            const input = await enterEditMode(el);
            input.value = 'new';
            input.dispatchEvent(new Event('input', { bubbles: true }));

            setTimeout(() => {
                input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            });

            const event = await oneEvent(el, 'sluggi-change');
            expect((event as CustomEvent).detail.value).to.equal('/new');
        });

        it('dispatches sluggi-edit-start when entering edit mode', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test"></sluggi-element>
            `);

            const editable = el.shadowRoot!.querySelector('.sluggi-editable') as HTMLElement;
            setTimeout(() => editable.click());

            const event = await oneEvent(el, 'sluggi-edit-start');
            expect(event).to.exist;
        });

        it('dispatches sluggi-edit-cancel on Escape', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test"></sluggi-element>
            `);

            const input = await enterEditMode(el);
            setTimeout(() => {
                input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            });

            const event = await oneEvent(el, 'sluggi-edit-cancel');
            expect(event).to.exist;
        });
    });

    describe('Form Integration', () => {
        it('syncs hidden input in light DOM with current value', async () => {
            const container = await fixture(html`
                <div>
                    <sluggi-element value="/test"></sluggi-element>
                    <input type="hidden" class="sluggi-hidden-field" name="data[slug]" value="/test" />
                </div>
            `);
            const el = container.querySelector('sluggi-element') as SluggiElement;
            const hidden = container.querySelector('.sluggi-hidden-field') as HTMLInputElement;

            expect(hidden.value).to.equal('/test');

            const input = await enterEditMode(el);
            input.value = 'updated';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(hidden.value).to.equal('/updated');
        });
    });

    describe('Spurious Change Event Prevention', () => {
        it('does NOT dispatch change on full-path field when canceling edit without changes', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <sluggi-element
                    value="/test"
                    full-path-feature-enabled
                    last-segment-only
                ></sluggi-element>
                <input type="hidden" class="sluggi-full-path-field" value="0" />
            `;
            document.body.appendChild(container);

            const el = container.querySelector('sluggi-element') as SluggiElement;
            const fullPathField = container.querySelector('.sluggi-full-path-field') as HTMLInputElement;
            await el.updateComplete;

            let changeEventCount = 0;
            fullPathField.addEventListener('change', () => changeEventCount++);

            const editable = el.shadowRoot!.querySelector('.sluggi-editable') as HTMLElement;
            editable.click();
            await el.updateComplete;

            const input = el.shadowRoot!.querySelector('input.sluggi-input') as HTMLInputElement;
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            await el.updateComplete;

            expect(changeEventCount, 'Should not dispatch change event on cancel without changes').to.equal(0);

            document.body.removeChild(container);
        });

        it('does NOT dispatch change on full-path field when saving same value', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <sluggi-element
                    value="/test"
                    full-path-feature-enabled
                    last-segment-only
                ></sluggi-element>
                <input type="hidden" class="sluggi-full-path-field" value="0" />
            `;
            document.body.appendChild(container);

            const el = container.querySelector('sluggi-element') as SluggiElement;
            const fullPathField = container.querySelector('.sluggi-full-path-field') as HTMLInputElement;
            await el.updateComplete;

            let changeEventCount = 0;
            fullPathField.addEventListener('change', () => changeEventCount++);

            const editable = el.shadowRoot!.querySelector('.sluggi-editable') as HTMLElement;
            editable.click();
            await el.updateComplete;

            const input = el.shadowRoot!.querySelector('input.sluggi-input') as HTMLInputElement;
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(changeEventCount, 'Should not dispatch change event when saving same value').to.equal(0);

            document.body.removeChild(container);
        });

        it('does NOT dispatch change when setProposal receives same value', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <sluggi-element
                    value="/test-slug"
                ></sluggi-element>
                <input type="hidden" class="sluggi-hidden-field" value="/test-slug" />
            `;
            document.body.appendChild(container);

            const el = container.querySelector('sluggi-element') as SluggiElement;
            const hiddenField = container.querySelector('.sluggi-hidden-field') as HTMLInputElement;
            await el.updateComplete;

            let changeEventCount = 0;
            hiddenField.addEventListener('change', () => changeEventCount++);

            el.setProposal('/test-slug', false);
            await el.updateComplete;

            expect(changeEventCount, 'Should not dispatch change when proposal equals current value').to.equal(0);
            expect(hiddenField.classList.contains('has-change'), 'Should not add has-change class').to.be.false;

            document.body.removeChild(container);
        });

        it('does NOT mark form as dirty when clicking into edit then clicking away without changes', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <sluggi-element
                    value="/test"
                    full-path-feature-enabled
                    last-segment-only
                ></sluggi-element>
                <input type="hidden" class="sluggi-hidden-field" value="/test" />
                <input type="hidden" class="sluggi-full-path-field" value="0" />
            `;
            document.body.appendChild(container);

            const el = container.querySelector('sluggi-element') as SluggiElement;
            const hiddenField = container.querySelector('.sluggi-hidden-field') as HTMLInputElement;
            const fullPathField = container.querySelector('.sluggi-full-path-field') as HTMLInputElement;
            await el.updateComplete;

            let hiddenFieldChanged = false;
            let fullPathFieldChanged = false;
            hiddenField.addEventListener('change', () => hiddenFieldChanged = true);
            fullPathField.addEventListener('change', () => fullPathFieldChanged = true);

            const editable = el.shadowRoot!.querySelector('.sluggi-editable') as HTMLElement;
            editable.click();
            await el.updateComplete;

            const input = el.shadowRoot!.querySelector('input.sluggi-input') as HTMLInputElement;
            input.dispatchEvent(new FocusEvent('blur', { bubbles: true }));
            await el.updateComplete;

            expect(hiddenFieldChanged, 'Hidden field should not be changed').to.be.false;
            expect(fullPathFieldChanged, 'Full path field should not be changed').to.be.false;

            document.body.removeChild(container);
        });

        it('removes has-change from sync field when toggled back to initial state', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <input data-sluggi-source data-formengine-input-name="data[pages][456][title]" value="Demo" />
                <sluggi-element
                    value="/demo"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                    sync-feature-enabled
                ></sluggi-element>
                <input type="hidden" class="sluggi-hidden-field" value="/demo" />
                <input type="hidden" class="sluggi-sync-field" value="0" />
            `;
            document.body.appendChild(container);

            const el = container.querySelector('sluggi-element') as SluggiElement;
            const syncField = container.querySelector('.sluggi-sync-field') as HTMLInputElement;
            await el.updateComplete;

            const syncToggle = el.shadowRoot!.querySelector('.sluggi-sync-toggle') as HTMLElement;

            syncToggle.click();
            await el.updateComplete;
            expect(syncField.classList.contains('has-change'), 'Sync field should have has-change after toggle ON').to.be.true;

            el.setProposal('/demo', false);
            await el.updateComplete;

            syncToggle.click();
            await el.updateComplete;
            expect(syncField.classList.contains('has-change'), 'Sync field should not have has-change after toggle back').to.be.false;

            document.body.removeChild(container);
        });

        it('removes has-change from lock field when toggled back to initial state', async () => {
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
                <input type="hidden" class="sluggi-hidden-field" value="/demo" />
                <input type="hidden" class="sluggi-lock-field" value="0" />
            `;
            document.body.appendChild(container);

            const el = container.querySelector('sluggi-element') as SluggiElement;
            const lockField = container.querySelector('.sluggi-lock-field') as HTMLInputElement;
            await el.updateComplete;

            const lockToggle = el.shadowRoot!.querySelector('.sluggi-lock-toggle') as HTMLElement;

            lockToggle.click();
            await el.updateComplete;
            expect(lockField.classList.contains('has-change'), 'Lock field should have has-change after toggle ON').to.be.true;

            lockToggle.click();
            await el.updateComplete;
            expect(lockField.classList.contains('has-change'), 'Lock field should not have has-change after toggle back').to.be.false;

            document.body.removeChild(container);
        });

        it('does NOT mark slug field as dirty when toggling sync ON then OFF without slug change', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <input data-sluggi-source data-formengine-input-name="data[pages][456][title]" value="Demo" />
                <sluggi-element
                    value="/demo"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                    sync-feature-enabled
                ></sluggi-element>
                <input type="hidden" class="sluggi-hidden-field" value="/demo" />
                <input type="hidden" class="sluggi-sync-field" value="0" />
            `;
            document.body.appendChild(container);

            const el = container.querySelector('sluggi-element') as SluggiElement;
            const hiddenField = container.querySelector('.sluggi-hidden-field') as HTMLInputElement;
            await el.updateComplete;

            const syncToggle = el.shadowRoot!.querySelector('.sluggi-sync-toggle') as HTMLElement;

            syncToggle.click();
            await el.updateComplete;

            el.setProposal('/demo', false);
            await el.updateComplete;

            syncToggle.click();
            await el.updateComplete;

            expect(hiddenField.classList.contains('has-change'), 'Slug hidden field should NOT have has-change when value did not change').to.be.false;

            document.body.removeChild(container);
        });

        it('does NOT mark form as dirty when regenerating slug that is already correct', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <input data-sluggi-source data-formengine-input-name="data[pages][456][title]" value="Demo" />
                <sluggi-element
                    value="/demo"
                    page-id="123"
                    record-id="456"
                    table-name="pages"
                    field-name="slug"
                ></sluggi-element>
                <input type="hidden" class="sluggi-hidden-field" value="/demo" />
            `;
            document.body.appendChild(container);

            const el = container.querySelector('sluggi-element') as SluggiElement;
            const hiddenField = container.querySelector('.sluggi-hidden-field') as HTMLInputElement;
            await el.updateComplete;

            el.setProposal('/demo', false);
            await el.updateComplete;

            expect(hiddenField.classList.contains('has-change'), 'Slug field should not have has-change').to.be.false;

            document.body.removeChild(container);
        });
    });
});
