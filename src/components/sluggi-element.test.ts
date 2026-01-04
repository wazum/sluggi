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

        it('shows placeholder when source field is empty on new page', async () => {
            const container = document.createElement('div');
            container.innerHTML = `
                <input data-sluggi-source data-formengine-input-name="data[pages][123][title]" value="" />
                <sluggi-element value="/parent/child" locked-prefix="/parent" command="new" record-id="123"></sluggi-element>
            `;
            document.body.appendChild(container);
            const el = container.querySelector('sluggi-element') as SluggiElement;
            await el.updateComplete;

            const editable = el.shadowRoot!.querySelector('.sluggi-editable');
            expect(editable?.textContent).to.contain('/new-page');
            expect(el.shadowRoot!.querySelector('.sluggi-placeholder')).to.exist;
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

        describe('preserve-underscore mode', () => {
            const testCases = [
                { input: 'hello_world', expected: 'hello_world', desc: 'preserves underscore' },
                { input: 'my_test_page', expected: 'my_test_page', desc: 'preserves multiple underscores' },
                { input: 'hello_world test', expected: 'hello_world-test', desc: 'preserves underscore, converts space to dash' },
                { input: 'hello_world+test', expected: 'hello_world-test', desc: 'preserves underscore, converts plus to dash' },
            ];

            testCases.forEach(({ input, expected, desc }) => {
                it(`${desc}: "${input}" → "${expected}"`, async () => {
                    const el = await fixture<SluggiElement>(html`
                        <sluggi-element value="/page" preserve-underscore></sluggi-element>
                    `);

                    const inputEl = await enterEditMode(el);
                    inputEl.value = input;
                    inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                    await el.updateComplete;

                    expect(inputEl.value).to.equal(expected);
                });
            });

            it('converts underscore to fallback when preserve disabled', async () => {
                const el = await fixture<SluggiElement>(html`
                    <sluggi-element value="/page"></sluggi-element>
                `);

                const inputEl = await enterEditMode(el);
                inputEl.value = 'hello_world';
                inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                await el.updateComplete;

                expect(inputEl.value).to.equal('hello-world');
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

        describe('segment trimming on save', () => {
            const segmentTrimCases = [
                { input: 'products/-electronics-/phones', expected: '/products/electronics/phones', desc: 'trims hyphens from each segment' },
                { input: '-about-/-our-team-/-contact-', expected: '/about/our-team/contact', desc: 'trims all segments with leading/trailing hyphens' },
                { input: 'services/consulting/strategy', expected: '/services/consulting/strategy', desc: 'keeps clean segments unchanged' },
                { input: '-welcome-page', expected: '/welcome-page', desc: 'trims leading hyphen from single segment' },
                { input: 'privacy-policy-', expected: '/privacy-policy', desc: 'trims trailing hyphen from single segment' },
            ];

            segmentTrimCases.forEach(({ input, expected, desc }) => {
                it(`${desc}: "${input}" → "${expected}"`, async () => {
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
            // No controls shown when hasNoControls is true (locked without lock-feature-enabled)
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

            // syncFeatureEnabled is false but isSynced is true
            expect(el.syncFeatureEnabled).to.be.false;
            expect(el.isSynced).to.be.true;

            titleInput.value = 'New Title';

            setTimeout(() => titleInput.dispatchEvent(new Event('change')));

            const event = await oneEvent(el, 'sluggi-request-proposal') as CustomEvent;
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

            // When user can't change anything, don't split into prefix/editable
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

            // When user can't change anything, don't split into prefix/editable
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

            // When user can't change anything, don't split into prefix/editable
            expect(el.shadowRoot!.querySelector('.sluggi-prefix')).to.be.null;
            const editable = el.shadowRoot!.querySelector('.sluggi-editable');
            expect(editable?.textContent?.trim()).to.equal('/parent/child/page');
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

            // Wait for timeout (2 seconds + buffer)
            await new Promise(resolve => setTimeout(resolve, 2100));
            await el.updateComplete;

            expect(copyBtn.classList.contains('is-copied')).to.be.false;
            expect(el.shadowRoot!.querySelector('.sluggi-copy-confirmation')).to.be.null;
        });
    });

    describe('Restriction Notes', () => {
        it('shows sync restriction note when isSynced', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    is-synced
                ></sluggi-element>
            `);

            const note = el.shadowRoot!.querySelector('.sluggi-note');
            expect(note).to.exist;
            expect(note?.textContent).to.contain('synchronized');
            expect(note?.getAttribute('role')).to.equal('status');
            expect(note?.getAttribute('aria-live')).to.equal('polite');
        });

        it('shows lock restriction note when isLocked', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    is-locked
                ></sluggi-element>
            `);

            const note = el.shadowRoot!.querySelector('.sluggi-note');
            expect(note).to.exist;
            expect(note?.textContent).to.contain('locked');
        });

        it('does not show restriction note when neither synced nor locked', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-note')).to.be.null;
        });

        it('shows full path info note when full path edit mode is active', async () => {
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

            const note = el.shadowRoot!.querySelector('.sluggi-note');
            expect(note).to.exist;
            expect(note?.textContent).to.contain('Full path editing');
        });
    });

    describe('Collapsed Controls', () => {
        it('shows burger menu trigger when collapsed-controls is set', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger');
            expect(menuTrigger).to.exist;
        });

        it('hides burger menu trigger when collapsed-controls is not set', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger');
            expect(menuTrigger).to.be.null;
        });

        it('expands controls on mouse enter', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const wrapper = el.shadowRoot!.querySelector('.sluggi-wrapper') as HTMLElement;
            wrapper.dispatchEvent(new MouseEvent('mouseenter'));
            await el.updateComplete;

            const collapsedMenu = el.shadowRoot!.querySelector('.sluggi-collapsed-menu');
            expect(collapsedMenu?.classList.contains('expanded')).to.be.true;
        });

        it('retracts controls after timeout on mouse leave', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const wrapper = el.shadowRoot!.querySelector('.sluggi-wrapper') as HTMLElement;
            wrapper.dispatchEvent(new MouseEvent('mouseenter'));
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-collapsed-menu.expanded')).to.exist;

            wrapper.dispatchEvent(new MouseEvent('mouseleave'));
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-collapsed-menu.expanded')).to.exist;

            await new Promise(resolve => setTimeout(resolve, 2100));
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-collapsed-menu.expanded')).to.be.null;
        });

        it('cancels retract timeout when mouse re-enters', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const wrapper = el.shadowRoot!.querySelector('.sluggi-wrapper') as HTMLElement;
            wrapper.dispatchEvent(new MouseEvent('mouseenter'));
            await el.updateComplete;

            wrapper.dispatchEvent(new MouseEvent('mouseleave'));
            await el.updateComplete;

            await new Promise(resolve => setTimeout(resolve, 1000));

            wrapper.dispatchEvent(new MouseEvent('mouseenter'));
            await el.updateComplete;

            await new Promise(resolve => setTimeout(resolve, 1500));
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-collapsed-menu.expanded')).to.exist;
        });

        it('always shows controls directly in edit mode', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const editBtn = el.shadowRoot!.querySelector('.sluggi-edit-btn') as HTMLElement;
            editBtn.click();
            await el.updateComplete;

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger');
            expect(menuTrigger).to.be.null;

            const saveBtn = el.shadowRoot!.querySelector('.sluggi-save-btn');
            expect(saveBtn).to.exist;
        });

        it('menu trigger is a button with proper ARIA attributes', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger') as HTMLButtonElement;
            expect(menuTrigger.tagName).to.equal('BUTTON');
            expect(menuTrigger.getAttribute('aria-expanded')).to.equal('false');
            expect(menuTrigger.getAttribute('aria-haspopup')).to.equal('true');
            expect(menuTrigger.getAttribute('aria-label')).to.exist;
        });

        it('expands controls on Enter key press', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger') as HTMLButtonElement;
            menuTrigger.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            const collapsedMenu = el.shadowRoot!.querySelector('.sluggi-collapsed-menu');
            expect(collapsedMenu?.classList.contains('expanded')).to.be.true;
            expect(menuTrigger.getAttribute('aria-expanded')).to.equal('true');
        });

        it('expands controls on Space key press', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger') as HTMLButtonElement;
            menuTrigger.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', bubbles: true }));
            await el.updateComplete;

            const collapsedMenu = el.shadowRoot!.querySelector('.sluggi-collapsed-menu');
            expect(collapsedMenu?.classList.contains('expanded')).to.be.true;
        });

        it('closes menu and focuses trigger on Escape from menu content', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger') as HTMLButtonElement;
            menuTrigger.click();
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-collapsed-menu.expanded')).to.exist;

            const wrapper = el.shadowRoot!.querySelector('.sluggi-wrapper') as HTMLElement;
            wrapper.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-collapsed-menu.expanded')).to.be.null;
        });

        it('menu content has inert attribute when collapsed', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuContent = el.shadowRoot!.querySelector('.sluggi-menu-content') as HTMLElement;
            expect(menuContent.hasAttribute('inert')).to.be.true;
        });

        it('menu content does not have inert attribute when expanded', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger') as HTMLButtonElement;
            menuTrigger.click();
            await el.updateComplete;

            const menuContent = el.shadowRoot!.querySelector('.sluggi-menu-content') as HTMLElement;
            expect(menuContent.hasAttribute('inert')).to.be.false;
        });

        it('menu trigger has tabindex -1 when expanded', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger') as HTMLButtonElement;
            expect(menuTrigger.getAttribute('tabindex')).to.equal('0');

            menuTrigger.click();
            await el.updateComplete;

            expect(menuTrigger.getAttribute('tabindex')).to.equal('-1');
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
    });

});
