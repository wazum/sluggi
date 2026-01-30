import '../sluggi-element.js';
import { fixture, html, expect, enterEditMode } from './helpers.js';
import type { SluggiElement } from './helpers.js';

describe('SluggiElement - Modes', () => {
    describe('Last Segment Only Mode', () => {
        it('splits value into prefix and last segment', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/parent/child/page" last-segment-only></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-prefix')?.textContent).to.contain('/parent/child');
            expect(el.shadowRoot!.querySelector('.sluggi-editable')?.textContent?.trim()).to.equal('/page');
        });

        it('does not duplicate prefix when editing multiple times', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/lorem/ipsum/dolor-sit" last-segment-only></sluggi-element>
            `);

            const input1 = await enterEditMode(el);
            input1.value = 'amet-page';
            input1.dispatchEvent(new Event('input', { bubbles: true }));
            input1.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/lorem/ipsum/amet-page');

            const input2 = await enterEditMode(el);
            input2.value = 'consectetur';
            input2.dispatchEvent(new Event('input', { bubbles: true }));
            input2.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/lorem/ipsum/consectetur');
        });

        it('does not duplicate prefix when lastSegmentOnly set dynamically', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/lorem/ipsum/dolor-sit"></sluggi-element>
            `);

            el.setAttribute('last-segment-only', '');
            await el.updateComplete;

            const input1 = await enterEditMode(el);
            input1.value = 'amet-page';
            input1.dispatchEvent(new Event('input', { bubbles: true }));
            input1.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/lorem/ipsum/amet-page');

            const input2 = await enterEditMode(el);
            input2.value = 'consectetur';
            input2.dispatchEvent(new Event('input', { bubbles: true }));
            input2.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/lorem/ipsum/consectetur');
        });

        it('handles external prefix combined with lastSegmentOnly', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element prefix="/site" value="/lorem/ipsum/dolor-sit" last-segment-only></sluggi-element>
            `);

            const input1 = await enterEditMode(el);
            input1.value = 'amet-page';
            input1.dispatchEvent(new Event('input', { bubbles: true }));
            input1.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/lorem/ipsum/amet-page');

            const input2 = await enterEditMode(el);
            input2.value = 'consectetur';
            input2.dispatchEvent(new Event('input', { bubbles: true }));
            input2.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/lorem/ipsum/consectetur');
        });

        it('setProposal keeps prefix visible for new page with single-segment value', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent-section"
                    locked-prefix="/parent-section"
                    last-segment-only
                    command="new"
                ></sluggi-element>
            `);

            el.setProposal('/parent-section/new-test-page');
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-prefix')).to.exist;
            expect(el.value).to.equal('/parent-section/new-test-page');
        });

        it('setProposal activates fullPathMode when shortened URL is regenerated to hierarchical path', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/short-url"
                    locked-prefix="/parent-section"
                    last-segment-only
                    full-path-feature-enabled
                ></sluggi-element>
            `);

            el.setProposal('/parent-section/short-url-page');
            await el.updateComplete;

            expect((el as any).isFullPathMode).to.be.true;
        });
    });

    describe('Locked State', () => {
        it('prevents editing when locked without toggle access', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test" is-locked></sluggi-element>
            `);

            const editable = el.shadowRoot!.querySelector('.sluggi-editable') as HTMLElement;
            editable?.click();
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('input.sluggi-input')).to.not.exist;
            expect(el.shadowRoot!.querySelector('.sluggi-edit-btn')).to.be.null;
            expect(el.shadowRoot!.querySelector('.sluggi-controls')?.children.length).to.equal(0);
        });

        it('shows disabled edit button when locked with toggle access', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test" is-locked lock-feature-enabled></sluggi-element>
            `);

            const editable = el.shadowRoot!.querySelector('.sluggi-editable') as HTMLElement;
            editable?.click();
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('input.sluggi-input')).to.not.exist;
            const editBtn = el.shadowRoot!.querySelector('.sluggi-edit-btn') as HTMLButtonElement;
            expect(editBtn).to.exist;
            expect(editBtn.disabled).to.be.true;
            expect(editBtn.classList.contains('is-disabled')).to.be.true;
        });
    });

    describe('Synced State Without Toggle Access', () => {
        it('triggers auto-sync on source field change when isSynced even without syncFeatureEnabled', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
            titleInput.value = 'Initial Title';
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/initial-title"
                    table-name="pages"
                    record-id="456"
                    command="edit"
                    is-synced
                ></sluggi-element>
            `);

            expect(el.syncFeatureEnabled).to.be.false;
            expect(el.isSynced).to.be.true;

            titleInput.value = 'New Title';

            const eventPromise = new Promise<CustomEvent>(resolve => {
                el.addEventListener('sluggi-request-proposal', (e) => resolve(e as CustomEvent), { once: true });
            });

            setTimeout(() => titleInput.dispatchEvent(new Event('change')));

            const event = await eventPromise;
            expect(event.detail.mode).to.equal('recreate');

            document.body.removeChild(titleInput);
        });
    });

    describe('Completely Readonly State', () => {
        it('shows full path without prefix split when locked without toggle access', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent/child/page"
                    locked-prefix="/parent"
                    is-locked
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-prefix')).to.be.null;
            const editable = el.shadowRoot!.querySelector('.sluggi-editable');
            expect(editable?.textContent?.trim()).to.equal('/parent/child/page');
        });

        it('shows full path without prefix split when synced without toggle access', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent/child/page"
                    locked-prefix="/parent"
                    is-synced
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-prefix')).to.be.null;
            const editable = el.shadowRoot!.querySelector('.sluggi-editable');
            expect(editable?.textContent?.trim()).to.equal('/parent/child/page');
        });

        it('shows full path without prefix split in last-segment-only mode when locked without toggle', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent/child/page"
                    last-segment-only
                    is-locked
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-prefix')).to.be.null;
            const editable = el.shadowRoot!.querySelector('.sluggi-editable');
            expect(editable?.textContent?.trim()).to.equal('/parent/child/page');
        });
    });
});
