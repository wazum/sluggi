import '../sluggi-element.js';
import { fixture, html, expect } from './helpers.js';
import type { SluggiElement } from './helpers.js';

describe('SluggiElement - Reserved Paths', () => {
    const WARNING_SELECTOR = '.sluggi-reserved-warning';

    it('does not render the warning when reserved-paths is missing', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element value="/about"></sluggi-element>
        `);
        expect(el.shadowRoot!.querySelector(WARNING_SELECTOR)).to.not.exist;
    });

    it('does not render the warning when reserved-paths is empty', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element value="/about" reserved-paths='[]'></sluggi-element>
        `);
        expect(el.shadowRoot!.querySelector(WARNING_SELECTOR)).to.not.exist;
    });

    it('renders the warning when value is an exact match for a reserved path', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element value="/api" reserved-paths='["/api"]'></sluggi-element>
        `);
        expect(el.shadowRoot!.querySelector(WARNING_SELECTOR)).to.exist;
    });

    it('renders the warning when value is a path under a reserved prefix', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element value="/api/v1" reserved-paths='["/api"]'></sluggi-element>
        `);
        expect(el.shadowRoot!.querySelector(WARNING_SELECTOR)).to.exist;
    });

    it('does not render the warning when value only shares a prefix but not a segment boundary', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element value="/api-docs" reserved-paths='["/api"]'></sluggi-element>
        `);
        expect(el.shadowRoot!.querySelector(WARNING_SELECTOR)).to.not.exist;
    });
});
