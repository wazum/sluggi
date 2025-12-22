import { fixture, html, expect, oneEvent } from '@open-wc/testing';
import './sluggi-element.js';
import type { SluggiElement } from './sluggi-element.js';

describe('SluggiElement', () => {
    async function enterEditMode(el: SluggiElement): Promise<HTMLInputElement> {
        const editable = el.shadowRoot!.querySelector('.sluggi-editable') as HTMLElement;
        editable.click();
        await el.updateComplete;
        return el.shadowRoot!.querySelector('input.sluggi-input') as HTMLInputElement;
    }

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

        it('shows placeholder appended to editable value when source field is empty', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <input data-sluggi-source data-formengine-input-name="data[pages][123][title]" value="" />
                <sluggi-element value="/parent/child" locked-prefix="/parent"></sluggi-element>
            `;
            document.body.appendChild(container);
            const el = container.querySelector('sluggi-element') as SluggiElement;
            await el.updateComplete;

            const editable = el.shadowRoot!.querySelector('.sluggi-editable');
            expect(editable?.textContent).to.contain('/child');
            expect(editable?.textContent).to.contain('/new-page');
            expect(el.shadowRoot!.querySelector('.sluggi-placeholder')).to.exist;
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

        it('should hide placeholder after TYPO3 FormEngine initializes source field', async () => {
            const sourceInput = document.createElement('input');
            sourceInput.setAttribute('data-sluggi-source', '');
            sourceInput.setAttribute('data-formengine-input-name', 'data[pages][26][title]');
            sourceInput.value = '';
            document.body.appendChild(sourceInput);

            const el = document.createElement('sluggi-element') as SluggiElement;
            el.setAttribute('value', '/organization/department/institute/about-page');
            el.setAttribute('locked-prefix', '/organization/department');
            el.setAttribute('command', 'edit');
            document.body.appendChild(el);

            await el.updateComplete;
            expect(el.shadowRoot!.querySelector('.sluggi-placeholder')).to.exist;

            sourceInput.value = 'About Us';
            sourceInput.dispatchEvent(new Event('formengine:input:initialized'));

            await new Promise(r => setTimeout(r, 200));
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-placeholder')).to.not.exist;

            document.body.removeChild(el);
            document.body.removeChild(sourceInput);
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

    describe('Input Sanitization', () => {
        describe('last-segment-only mode replaces slashes with fallback character', () => {
            const testCases = [
                { input: 'new/path/slashes', expected: 'new-path-slashes', desc: 'replaces internal slashes' },
                { input: '/already/has/slashes', expected: 'already-has-slashes', desc: 'replaces all slashes' },
                { input: 'no-slashes', expected: 'no-slashes', desc: 'keeps input without slashes' },
            ];

            testCases.forEach(({ input, expected, desc }) => {
                it(`${desc}: "${input}" → "${expected}"`, async () => {
                    const el = await fixture<SluggiElement>(html`
                        <sluggi-element value="/page" last-segment-only></sluggi-element>
                    `);

                    const inputEl = await enterEditMode(el);
                    inputEl.value = input;
                    inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                    await el.updateComplete;

                    expect(inputEl.value).to.equal(expected);
                });
            });

            it('replaces slashes with fallback character on save', async () => {
                const el = await fixture<SluggiElement>(html`
                    <sluggi-element value="/parent/child" last-segment-only></sluggi-element>
                `);

                const inputEl = await enterEditMode(el);
                inputEl.value = 'new/segment/with/slashes';
                inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                inputEl.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
                await el.updateComplete;

                // Slashes should be replaced with hyphens, not create new segments
                expect(el.value).to.equal('/parent/new-segment-with-slashes');
                expect(el.value).not.to.contain('/new/segment');
            });
        });

        describe('invalid characters (TYPO3 slug rules)', () => {
            const testCases = [
                { input: 'hello@world', expected: 'helloworld', desc: 'strips @' },
                { input: 'hello!world', expected: 'helloworld', desc: 'strips !' },
                { input: 'hello#world', expected: 'helloworld', desc: 'strips #' },
                { input: 'hello$world', expected: 'helloworld', desc: 'strips $' },
                { input: 'hello%world', expected: 'helloworld', desc: 'strips %' },
                { input: 'hello&world', expected: 'helloworld', desc: 'strips &' },
                { input: 'hello*world', expected: 'helloworld', desc: 'strips *' },
                { input: 'hello(world)', expected: 'helloworld', desc: 'strips parentheses' },
                { input: 'hello[world]', expected: 'helloworld', desc: 'strips brackets' },
                { input: 'hello{world}', expected: 'helloworld', desc: 'strips braces' },
                { input: 'hello<world>', expected: 'helloworld', desc: 'strips angle brackets' },
                { input: 'hello?world', expected: 'helloworld', desc: 'strips ?' },
                { input: 'hello=world', expected: 'helloworld', desc: 'strips =' },
                { input: 'hello+world', expected: 'hello-world', desc: 'converts + to fallbackCharacter' },
                { input: 'hello world', expected: 'hello-world', desc: 'converts space to fallbackCharacter' },
                { input: 'hello_world', expected: 'hello-world', desc: 'converts _ to fallbackCharacter' },
                { input: 'hello--world', expected: 'hello-world', desc: 'collapses multiple fallbackCharacters' },
                { input: 'UPPERCASE', expected: 'uppercase', desc: 'converts to lowercase' },
            ];

            testCases.forEach(({ input, expected, desc }) => {
                it(`${desc}: "${input}" → "${expected}"`, async () => {
                    const el = await fixture<SluggiElement>(html`
                        <sluggi-element value="/page"></sluggi-element>
                    `);

                    const inputEl = await enterEditMode(el);
                    inputEl.value = input;
                    inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                    await el.updateComplete;

                    expect(inputEl.value).to.equal(expected);
                });
            });
        });

        describe('custom fallback character', () => {
            const testCases = [
                { input: 'hello world', expected: 'hello_world', desc: 'converts space to custom fallback' },
                { input: 'hello_world', expected: 'hello_world', desc: 'converts _ to custom fallback' },
                { input: 'hello-world', expected: 'hello_world', desc: 'converts - to custom fallback' },
                { input: 'hello__world', expected: 'hello_world', desc: 'collapses multiple custom fallbacks' },
            ];

            testCases.forEach(({ input, expected, desc }) => {
                it(`${desc}: "${input}" → "${expected}"`, async () => {
                    const el = await fixture<SluggiElement>(html`
                        <sluggi-element value="/page" fallback-character="_"></sluggi-element>
                    `);

                    const inputEl = await enterEditMode(el);
                    inputEl.value = input;
                    inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                    await el.updateComplete;

                    expect(inputEl.value).to.equal(expected);
                });
            });
        });

        describe('valid characters', () => {
            const testCases = [
                { input: 'hello-world', expected: 'hello-world', desc: 'keeps hyphens' },
                { input: 'hello123', expected: 'hello123', desc: 'keeps numbers' },
                { input: 'über', expected: 'über', desc: 'keeps unicode letters' },
                { input: 'café', expected: 'café', desc: 'keeps accented letters' },
            ];

            testCases.forEach(({ input, expected, desc }) => {
                it(`${desc}: "${input}" → "${expected}"`, async () => {
                    const el = await fixture<SluggiElement>(html`
                        <sluggi-element value="/page"></sluggi-element>
                    `);

                    const inputEl = await enterEditMode(el);
                    inputEl.value = input;
                    inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                    await el.updateComplete;

                    expect(inputEl.value).to.equal(expected);
                });
            });
        });

        describe('hyphen handling', () => {
            const duringEditCases = [
                { input: 'test-', desc: 'trailing' },
                { input: '-test', desc: 'leading' },
            ];

            duringEditCases.forEach(({ input, desc }) => {
                it(`allows ${desc} hyphens during editing`, async () => {
                    const el = await fixture<SluggiElement>(html`
                        <sluggi-element value="/page"></sluggi-element>
                    `);

                    const inputEl = await enterEditMode(el);
                    inputEl.value = input;
                    inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                    await el.updateComplete;

                    expect(inputEl.value).to.equal(input);
                });
            });

            const onSaveCases = [
                { input: 'test-', expected: '/test', desc: 'trailing' },
                { input: '-test', expected: '/test', desc: 'leading' },
            ];

            onSaveCases.forEach(({ input, expected, desc }) => {
                it(`trims ${desc} hyphens on save`, async () => {
                    const el = await fixture<SluggiElement>(html`
                        <sluggi-element value="/page"></sluggi-element>
                    `);

                    const inputEl = await enterEditMode(el);
                    inputEl.value = input;
                    inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                    inputEl.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
                    await el.updateComplete;

                    expect(el.value).to.equal(expected);
                });
            });
        });
    });

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

    });

    describe('Locked State', () => {
        it('prevents editing when locked', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test" is-locked></sluggi-element>
            `);

            const editable = el.shadowRoot!.querySelector('.sluggi-editable') as HTMLElement;
            editable?.click();
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('input.sluggi-input')).to.not.exist;
            expect(el.shadowRoot!.querySelector('.sluggi-lock-icon')).to.exist;
            expect(el.shadowRoot!.querySelector('.sluggi-edit-btn')).to.not.exist;
        });
    });

    describe('Regenerate Button', () => {
        const visibilityTestCases = [
            {
                desc: 'visible when source fields have values',
                setup: () => {
                    const input = document.createElement('input');
                    input.setAttribute('data-sluggi-source', '');
                    input.setAttribute('data-formengine-input-name', 'data[pages][123][title]');
                    input.value = 'Test Title';
                    document.body.appendChild(input);
                    return input;
                },
                attrs: 'value="/test"',
                expected: true,
            },
            {
                desc: 'hidden when source fields are empty',
                setup: () => {
                    const input = document.createElement('input');
                    input.setAttribute('data-sluggi-source', '');
                    input.setAttribute('data-formengine-input-name', 'data[pages][123][title]');
                    input.value = '';
                    document.body.appendChild(input);
                    return input;
                },
                attrs: 'value="/test"',
                expected: false,
            },
            {
                desc: 'visible when has-post-modifiers is set',
                setup: () => null,
                attrs: 'value="/test" has-post-modifiers',
                expected: true,
            },
            {
                desc: 'hidden when no source fields or post-modifiers',
                setup: () => null,
                attrs: 'value="/test"',
                expected: false,
            },
            {
                desc: 'hidden when locked',
                setup: () => null,
                attrs: 'value="/test" is-locked',
                expected: false,
            },
        ];

        visibilityTestCases.forEach(({ desc, setup, attrs, expected }) => {
            it(desc, async () => {
                const element = setup();
                const el = await fixture<SluggiElement>(
                    html`<sluggi-element .value="${'/test'}" ?has-post-modifiers="${attrs.includes('has-post-modifiers')}" ?is-locked="${attrs.includes('is-locked')}"></sluggi-element>`
                );

                if (expected) {
                    expect(el.shadowRoot!.querySelector('.sluggi-regenerate-btn')).to.exist;
                } else {
                    expect(el.shadowRoot!.querySelector('.sluggi-regenerate-btn')).to.not.exist;
                }

                if (element) document.body.removeChild(element);
            });
        });

        it('hidden when synced with source fields present', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][123][title]');
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test" is-synced></sluggi-element>
            `);
            expect(el.shadowRoot!.querySelector('.sluggi-regenerate-btn')).to.not.exist;

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
    });

    describe('Source Field Listening', () => {
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

    describe('Accessibility', () => {
        it('has proper ARIA attributes for keyboard navigation', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test"></sluggi-element>
            `);

            const editable = el.shadowRoot!.querySelector('.sluggi-editable');
            expect(editable?.getAttribute('role')).to.equal('button');
            expect(editable?.getAttribute('tabindex')).to.equal('0');

            const input = await enterEditMode(el);
            expect(input.getAttribute('aria-label')).to.exist;
        });
    });
});
