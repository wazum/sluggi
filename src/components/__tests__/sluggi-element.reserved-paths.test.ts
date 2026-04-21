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

    it('marks the hidden slug input with has-error when the slug is reserved', async () => {
        const wrapper = await fixture<HTMLDivElement>(html`
            <div>
                <sluggi-element value="/api" reserved-paths='["/api"]'></sluggi-element>
                <input type="hidden" class="sluggi-hidden-field" name="data[pages][1][slug]" value="/api"
                    data-formengine-input-name="data[pages][1][slug]" />
            </div>
        `);

        const hidden = wrapper.querySelector('.sluggi-hidden-field') as HTMLInputElement;
        expect(hidden.classList.contains('has-error')).to.equal(true);
        expect(hidden.getAttribute('aria-invalid')).to.equal('true');
    });

    it('removes has-error once the slug stops matching a reserved path', async () => {
        const wrapper = await fixture<HTMLDivElement>(html`
            <div>
                <sluggi-element value="/api" reserved-paths='["/api"]'></sluggi-element>
                <input type="hidden" class="sluggi-hidden-field" name="data[pages][1][slug]" value="/api"
                    data-formengine-input-name="data[pages][1][slug]" />
            </div>
        `);

        const sluggi = wrapper.querySelector('sluggi-element') as SluggiElement;
        sluggi.value = '/about';
        await sluggi.updateComplete;

        const hidden = wrapper.querySelector('.sluggi-hidden-field') as HTMLInputElement;
        expect(hidden.classList.contains('has-error')).to.equal(false);
        expect(hidden.getAttribute('aria-invalid')).to.not.equal('true');
    });

    it('marks the FormEngine label with has-error so the red exclamation indicator lights up', async () => {
        const wrapper = await fixture<HTMLFieldSetElement>(html`
            <fieldset class="t3js-formengine-validation-marker">
                <legend class="t3js-formengine-label form-label">URL Path</legend>
                <sluggi-element value="/api" reserved-paths='["/api"]'></sluggi-element>
                <input type="hidden" class="sluggi-hidden-field" name="data[pages][1][slug]" value="/api"
                    data-formengine-input-name="data[pages][1][slug]" />
            </fieldset>
        `);

        const legend = wrapper.querySelector('.t3js-formengine-label') as HTMLLegendElement;
        expect(legend.classList.contains('has-error')).to.equal(true);
    });

    it('dispatches t3-formengine-postfieldvalidation with isValid=false on the ancestor form when reserved', async () => {
        const events: Array<{ isValid: boolean; field: Element }> = [];
        document.addEventListener('t3-formengine-postfieldvalidation', function capture(e) {
            const detail = (e as CustomEvent).detail;
            events.push({ isValid: detail.isValid, field: detail.field });
        }, { capture: true, once: false });

        await fixture<HTMLFormElement>(html`
            <form>
                <sluggi-element value="/api" reserved-paths='["/api"]'></sluggi-element>
                <input type="hidden" class="sluggi-hidden-field" name="data[pages][1][slug]" value="/api"
                    data-formengine-input-name="data[pages][1][slug]" />
            </form>
        `);

        const invalidEvent = events.find(e => !e.isValid);
        expect(invalidEvent, 'FormEngine must be told the field is invalid').to.exist;
        expect((invalidEvent!.field as HTMLInputElement).classList.contains('sluggi-hidden-field')).to.equal(true);
    });
});
