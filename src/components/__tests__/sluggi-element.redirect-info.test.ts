import '../sluggi-element.js';
import { fixture, html, expect } from './helpers.js';
import type { SluggiElement } from './helpers.js';

describe('SluggiElement - Redirect Info', () => {
    it('renders redirect info note when redirect-count and redirects-module-url are set', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                redirect-count="3"
                redirects-module-url="/typo3/module/link-management/redirects?demand%5Btarget%5D=t3%3A%2F%2Fpage%3Fuid%3D5"
            ></sluggi-element>
        `);

        const note = el.shadowRoot!.querySelector('.sluggi-redirect-info');
        expect(note).to.exist;
        expect(note?.textContent).to.contain('3');

        const link = note?.querySelector('a');
        expect(link).to.exist;
        expect(link?.getAttribute('href')).to.contain('redirects');
        expect(link?.getAttribute('target')).to.equal('_top');
    });

    it('renders singular label when redirect-count is 1', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                redirect-count="1"
                redirects-module-url="/typo3/module/redirects"
            ></sluggi-element>
        `);

        const note = el.shadowRoot!.querySelector('.sluggi-redirect-info');
        expect(note).to.exist;
        expect(note?.textContent).to.contain('1 redirect for');
    });

    it('renders plural label when redirect-count is more than 1', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                redirect-count="3"
                redirects-module-url="/typo3/module/redirects"
            ></sluggi-element>
        `);

        const note = el.shadowRoot!.querySelector('.sluggi-redirect-info');
        expect(note).to.exist;
        expect(note?.textContent).to.contain('3 redirects for');
    });

    it('does not render redirect info when redirect-count is 0', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                redirect-count="0"
            ></sluggi-element>
        `);

        expect(el.shadowRoot!.querySelector('.sluggi-redirect-info')).to.be.null;
    });

    it('does not render redirect info when redirects-module-url is missing', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                redirect-count="5"
            ></sluggi-element>
        `);

        expect(el.shadowRoot!.querySelector('.sluggi-redirect-info')).to.be.null;
    });
});
