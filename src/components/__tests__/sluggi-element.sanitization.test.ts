import '../sluggi-element.js';
import { fixture, html, expect, enterEditMode } from './helpers.js';
import type { SluggiElement } from './helpers.js';

describe('SluggiElement - Input Sanitization', () => {
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
