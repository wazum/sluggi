import '../sluggi-element.js';
import { fixture, html, expect } from './helpers.js';
import type { SluggiElement } from './helpers.js';

describe('SluggiElement - redirect modal skipped for hidden pages', () => {
    it('declares the page-hidden attribute as a boolean property', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element value="/foo" record-id="1" page-id="1" page-hidden></sluggi-element>
        `);
        expect((el as unknown as { pageHidden: boolean }).pageHidden).to.equal(true);
    });

    it('defaults page-hidden to false when the attribute is absent', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element value="/foo" record-id="1" page-id="1"></sluggi-element>
        `);
        expect((el as unknown as { pageHidden: boolean }).pageHidden).to.equal(false);
    });
});
