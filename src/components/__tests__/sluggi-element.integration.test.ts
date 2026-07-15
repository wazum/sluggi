import '../sluggi-element.js';
import { fixture, html, expect, oneEvent, enterEditMode } from './helpers.js';
import type { SluggiElement } from './helpers.js';
import Notification from '@typo3/backend/notification.js';

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

        it('hides placeholder after accepting conflict suggestion on new record', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent/child"
                    locked-prefix="/parent"
                    command="new"
                ></sluggi-element>
            `);

            (el as any).hasSourceFields = true;
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-placeholder'), 'placeholder should show initially').to.exist;

            // Set conflict via setProposal (Modal.confirm is no-op mock)
            el.setProposal('/parent/child/my-new-page', true, '/parent/child/existing');
            await el.updateComplete;

            expect(el.hasConflict).to.be.true;

            (el as any).useSuggestion();
            await el.updateComplete;

            expect(el.value).to.equal('/parent/child/my-new-page');
            const placeholderStillVisible = el.shadowRoot!.querySelector('.sluggi-placeholder') !== null;
            expect(placeholderStillVisible, 'placeholder must hide after accepting conflict suggestion').to.be.false;
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

    describe('Redirect Control Save Interception', () => {
        it('still intercepts the save button after one of several elements disconnects', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <form>
                    <sluggi-element value="/page-a" record-id="1" page-id="1" redirect-control></sluggi-element>
                    <sluggi-element value="/page-b" record-id="2" page-id="2" redirect-control></sluggi-element>
                    <button name="_savedok" type="button">Save</button>
                </form>
            `;
            document.body.appendChild(container);
            const elements = Array.from(container.querySelectorAll('sluggi-element')) as SluggiElement[];
            await Promise.all(elements.map((element) => element.updateComplete));
            for (const element of elements) {
                element.value = `${element.value}-changed`;
            }

            elements[0].remove();

            const button = container.querySelector('button[name="_savedok"]') as HTMLButtonElement;
            const clickEvent = new MouseEvent('click', { bubbles: true, cancelable: true });
            button.dispatchEvent(clickEvent);

            expect(clickEvent.defaultPrevented).to.equal(true);

            document.body.removeChild(container);
        });

        it('keeps blocking save clicks while the redirect modal decision is pending', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <form>
                    <sluggi-element value="/page-a" record-id="1" page-id="1" redirect-control></sluggi-element>
                    <button name="_savedok" type="button">Save</button>
                </form>
            `;
            document.body.appendChild(container);
            const element = container.querySelector('sluggi-element') as SluggiElement;
            await element.updateComplete;
            element.value = '/page-a-changed';

            const button = container.querySelector('button[name="_savedok"]') as HTMLButtonElement;
            const firstClick = new MouseEvent('click', { bubbles: true, cancelable: true });
            button.dispatchEvent(firstClick);
            expect(firstClick.defaultPrevented, 'first click opens the modal and is blocked').to.equal(true);

            const secondClick = new MouseEvent('click', { bubbles: true, cancelable: true });
            button.dispatchEvent(secondClick);
            expect(secondClick.defaultPrevented, 'second click while the modal is pending must be blocked too').to.equal(true);

            document.body.removeChild(container);
        });

        it('intercepts other save submitters like save-and-close', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <form>
                    <sluggi-element value="/page-a" record-id="1" page-id="1" redirect-control></sluggi-element>
                    <button name="_saveandclosedok" type="button">Save and close</button>
                </form>
            `;
            document.body.appendChild(container);
            const element = container.querySelector('sluggi-element') as SluggiElement;
            await element.updateComplete;
            element.value = '/page-a-changed';

            const button = container.querySelector('button[name="_saveandclosedok"]') as HTMLButtonElement;
            const clickEvent = new MouseEvent('click', { bubbles: true, cancelable: true });
            button.dispatchEvent(clickEvent);

            expect(clickEvent.defaultPrevented).to.equal(true);

            document.body.removeChild(container);
        });

        it('no longer intercepts the save button after the last element disconnects', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <form>
                    <sluggi-element value="/page-a" record-id="1" page-id="1" redirect-control></sluggi-element>
                    <sluggi-element value="/page-b" record-id="2" page-id="2" redirect-control></sluggi-element>
                    <button name="_savedok" type="button">Save</button>
                </form>
            `;
            document.body.appendChild(container);
            const elements = Array.from(container.querySelectorAll('sluggi-element')) as SluggiElement[];
            await Promise.all(elements.map((element) => element.updateComplete));
            for (const element of elements) {
                element.value = `${element.value}-changed`;
                element.remove();
            }

            const button = container.querySelector('button[name="_savedok"]') as HTMLButtonElement;
            const clickEvent = new MouseEvent('click', { bubbles: true, cancelable: true });
            button.dispatchEvent(clickEvent);

            expect(clickEvent.defaultPrevented).to.equal(false);

            document.body.removeChild(container);
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

    describe('Proposal error handling', () => {
        let originalFetch: typeof window.fetch;
        let originalTypo3: any;

        beforeEach(() => {
            originalFetch = window.fetch;
            originalTypo3 = (window as any).TYPO3;
            (window as any).TYPO3 = { settings: { ajaxUrls: { record_slug_suggest: '/fake-endpoint' } } };
            (Notification as any)._reset();
        });

        afterEach(() => {
            window.fetch = originalFetch;
            (window as any).TYPO3 = originalTypo3;
        });

        it('shows a warning notification when the slug proposal request fails', async () => {
            window.fetch = async () => new Response('', { status: 500 });

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    record-id="123"
                    page-id="1"
                    table-name="pages"
                    field-name="slug"
                    labels='{"error.proposalFailed.title":"URL preview unavailable","error.proposalFailed.message":"Could not update the URL preview. Please check your connection and try again."}'
                ></sluggi-element>
            `);

            await el.sendSlugProposal('manual');

            const calls = (Notification as any)._calls as Array<{ type: string; title: string; message: string }>;
            expect(calls).to.have.lengthOf(1);
            expect(calls[0].type).to.equal('warning');
            expect(calls[0].title).to.equal('URL preview unavailable');
            expect(calls[0].message).to.contain('Could not update');
        });

        it('shows a warning and keeps the value when the proposal response is malformed', async () => {
            window.fetch = async () => new Response(JSON.stringify({ unexpected: true }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            });

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    record-id="123"
                    page-id="1"
                    table-name="pages"
                    field-name="slug"
                ></sluggi-element>
            `);

            await el.sendSlugProposal('manual');

            expect(el.value, 'value must not be clobbered by a malformed response').to.equal('/test');
            const calls = (Notification as any)._calls as Array<{ type: string }>;
            expect(calls).to.have.lengthOf(1);
            expect(calls[0].type).to.equal('warning');
        });
    });

    describe('Proposal request sequencing', () => {
        let originalFetch: typeof window.fetch;
        let originalTypo3: any;

        beforeEach(() => {
            originalFetch = window.fetch;
            originalTypo3 = (window as any).TYPO3;
            (window as any).TYPO3 = { settings: { ajaxUrls: { record_slug_suggest: '/fake-endpoint' } } };
        });

        afterEach(() => {
            window.fetch = originalFetch;
            (window as any).TYPO3 = originalTypo3;
        });

        it('applies the newest proposal when a second request starts while one is in flight', async () => {
            let resolveFirstResponse!: (response: Response) => void;
            const firstResponse = new Promise<Response>((resolve) => {
                resolveFirstResponse = resolve;
            });
            let requestCount = 0;
            window.fetch = () => {
                requestCount++;
                if (requestCount === 1) {
                    return firstResponse;
                }
                return Promise.resolve(
                    new Response(JSON.stringify({ proposal: '/second', hasConflicts: false }), { status: 200 })
                );
            };

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    record-id="123"
                    page-id="1"
                    table-name="pages"
                    field-name="slug"
                ></sluggi-element>
            `);

            const firstCall = el.sendSlugProposal('manual');
            const secondCall = el.sendSlugProposal('manual');
            resolveFirstResponse(
                new Response(JSON.stringify({ proposal: '/first', hasConflicts: false }), { status: 200 })
            );
            await Promise.all([firstCall, secondCall]);

            expect(el.value).to.equal('/second');
        });

        it('ignores the failure of a superseded proposal request', async () => {
            let rejectFirstResponse!: (error: Error) => void;
            const firstResponse = new Promise<Response>((_, reject) => {
                rejectFirstResponse = reject;
            });
            let requestCount = 0;
            window.fetch = () => {
                requestCount++;
                if (requestCount === 1) {
                    return firstResponse;
                }
                return Promise.resolve(
                    new Response(JSON.stringify({ proposal: '/second', hasConflicts: false }), { status: 200 })
                );
            };
            (Notification as any)._reset();

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    record-id="123"
                    page-id="1"
                    table-name="pages"
                    field-name="slug"
                ></sluggi-element>
            `);

            const firstCall = el.sendSlugProposal('manual');
            const secondCall = el.sendSlugProposal('manual');
            rejectFirstResponse(new Error('connection lost'));
            await Promise.all([firstCall, secondCall]);

            expect((Notification as any)._calls).to.have.lengthOf(0);
            expect(el.value).to.equal('/second');
        });

        it('discards an in-flight proposal when sync is toggled off before it resolves', async () => {
            let resolveResponse!: (response: Response) => void;
            const pendingResponse = new Promise<Response>((resolve) => {
                resolveResponse = resolve;
            });
            window.fetch = () => pendingResponse;

            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][123][title]');
            titleInput.value = 'Demo';
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    record-id="123"
                    page-id="1"
                    table-name="pages"
                    field-name="slug"
                    sync-feature-enabled
                ></sluggi-element>
            `);

            const syncToggle = el.shadowRoot!.querySelector('.sluggi-sync-toggle') as HTMLElement;
            syncToggle.click();
            await el.updateComplete;
            syncToggle.click();
            resolveResponse(
                new Response(JSON.stringify({ proposal: '/synced-proposal', hasConflicts: false }), { status: 200 })
            );
            await new Promise((resolve) => setTimeout(resolve));
            await new Promise((resolve) => setTimeout(resolve));

            expect(el.value).to.equal('/test');
            expect(el.loading).to.be.false;

            document.body.removeChild(titleInput);
        });

        it('discards an in-flight proposal when the slug is locked before it resolves', async () => {
            let resolveResponse!: (response: Response) => void;
            const pendingResponse = new Promise<Response>((resolve) => {
                resolveResponse = resolve;
            });
            window.fetch = () => pendingResponse;

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    record-id="123"
                    page-id="1"
                    table-name="pages"
                    field-name="slug"
                    lock-feature-enabled
                ></sluggi-element>
            `);

            const proposalCall = el.sendSlugProposal('recreate');
            const lockToggle = el.shadowRoot!.querySelector('.sluggi-lock-toggle') as HTMLElement;
            lockToggle.click();
            resolveResponse(
                new Response(JSON.stringify({ proposal: '/synced-proposal', hasConflicts: false }), { status: 200 })
            );
            await proposalCall;

            expect(el.value).to.equal('/test');
            expect(el.loading).to.be.false;
        });

        it('discards an in-flight proposal when editing is cancelled before it resolves', async () => {
            let resolveResponse!: (response: Response) => void;
            const pendingResponse = new Promise<Response>((resolve) => {
                resolveResponse = resolve;
            });
            window.fetch = () => pendingResponse;

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    record-id="123"
                    page-id="1"
                    table-name="pages"
                    field-name="slug"
                ></sluggi-element>
            `);

            const editable = el.shadowRoot!.querySelector('.sluggi-editable') as HTMLElement;
            editable.click();
            await el.updateComplete;

            const proposalCall = el.sendSlugProposal('recreate');
            const input = el.shadowRoot!.querySelector('input.sluggi-input') as HTMLInputElement;
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            resolveResponse(
                new Response(JSON.stringify({ proposal: '/synced-proposal', hasConflicts: false }), { status: 200 })
            );
            await proposalCall;

            expect(el.value).to.equal('/test');
            expect(el.loading).to.be.false;
        });
    });
});
