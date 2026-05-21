import '../sluggi-element.js';
import { fixture, html, expect } from './helpers.js';
import type { SluggiElement } from './helpers.js';

const hiddenNoteText = 'This page is hidden — saving will not create a redirect for the changed URL path.';

const labels = JSON.stringify({
    syncRestrictionNote: 'The URL path is automatically synchronized with the source fields.',
    lockRestrictionNote: 'The URL path is locked and cannot be edited.',
    'notification.note.hiddenPageNoRedirect': hiddenNoteText,
});

function buildContainerWithSourceField(elementAttributes: Record<string, string>, recordId = '1'): { container: HTMLDivElement; element: SluggiElement } {
    const container = document.createElement('div');
    const source = document.createElement('input');
    source.setAttribute('data-sluggi-source', '');
    source.setAttribute('data-formengine-input-name', `data[pages][${recordId}][title]`);
    source.value = 'Foo';
    container.appendChild(source);

    const element = document.createElement('sluggi-element') as SluggiElement;
    for (const [name, value] of Object.entries(elementAttributes)) {
        element.setAttribute(name, value);
    }
    container.appendChild(element);
    document.body.appendChild(container);
    return { container, element };
}

describe('SluggiElement - hidden-page redirect note', () => {
    it('renders the note when page-hidden and redirect-control are both set on an editable slug', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                record-id="1"
                page-id="1"
                page-hidden
                redirect-control
                .labels="${labels}"
            ></sluggi-element>
        `);

        const note = el.shadowRoot!.querySelector('.sluggi-hidden-page-note');
        expect(note, 'hidden-page note must exist').to.exist;
        expect(note?.textContent?.trim()).to.equal(hiddenNoteText);
    });

    it('does not render the note when page is not hidden', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                record-id="1"
                page-id="1"
                redirect-control
                .labels="${labels}"
            ></sluggi-element>
        `);

        expect(el.shadowRoot!.querySelector('.sluggi-hidden-page-note')).to.equal(null);
    });

    it('does not render the note when redirect control is disabled', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                record-id="1"
                page-id="1"
                page-hidden
                .labels="${labels}"
            ></sluggi-element>
        `);

        expect(el.shadowRoot!.querySelector('.sluggi-hidden-page-note')).to.equal(null);
    });

    it('does not render the note when the slug is locked — editor cannot change it', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                record-id="1"
                page-id="1"
                page-hidden
                redirect-control
                is-locked
                lock-feature-enabled
                .labels="${labels}"
            ></sluggi-element>
        `);

        expect(el.shadowRoot!.querySelector('.sluggi-hidden-page-note')).to.equal(null);
    });

    it('does not render the note when auto-sync is on and no source fields are visible — slug cannot change here', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                record-id="1"
                page-id="1"
                page-hidden
                redirect-control
                is-synced
                sync-feature-enabled
                .labels="${labels}"
            ></sluggi-element>
        `);

        expect(el.shadowRoot!.querySelector('.sluggi-hidden-page-note')).to.equal(null);
    });

    it('renders the note alongside the sync restriction note when source fields ARE present (full edit form)', async () => {
        const { container, element } = buildContainerWithSourceField({
            value: '/test',
            'record-id': '1',
            'page-id': '1',
            'page-hidden': '',
            'redirect-control': '',
            'is-synced': '',
            'sync-feature-enabled': '',
            'required-source-fields': 'title',
            labels,
        });
        await element.updateComplete;

        try {
            const notes = Array.from(element.shadowRoot!.querySelectorAll('.sluggi-note'));
            const syncNote = notes.find((n) => n.textContent?.includes('automatically synchronized'));
            const hiddenNote = element.shadowRoot!.querySelector('.sluggi-hidden-page-note');
            expect(syncNote, 'sync restriction note must still render').to.exist;
            expect(hiddenNote, 'hidden-page note must render too').to.exist;
            expect(syncNote!.compareDocumentPosition(hiddenNote!) & Node.DOCUMENT_POSITION_FOLLOWING)
                .to.equal(Node.DOCUMENT_POSITION_FOLLOWING, 'hidden-page note must appear AFTER the sync note');
        } finally {
            document.body.removeChild(container);
        }
    });

    it('renders the note after the redirect-info line', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                record-id="1"
                page-id="1"
                page-hidden
                redirect-control
                redirect-count="2"
                redirects-module-url="/typo3/module/redirects"
                .labels="${labels}"
            ></sluggi-element>
        `);

        const infoNote = el.shadowRoot!.querySelector('.sluggi-redirect-info');
        const hiddenNote = el.shadowRoot!.querySelector('.sluggi-hidden-page-note');
        expect(infoNote, 'redirect-info must still render').to.exist;
        expect(hiddenNote, 'hidden-page note must render too').to.exist;
        expect(infoNote!.compareDocumentPosition(hiddenNote!) & Node.DOCUMENT_POSITION_FOLLOWING)
            .to.equal(Node.DOCUMENT_POSITION_FOLLOWING, 'hidden-page note must appear AFTER the redirect-info note');
    });

    it('renders standalone when no other note is present', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/test"
                record-id="1"
                page-id="1"
                page-hidden
                redirect-control
                .labels="${labels}"
            ></sluggi-element>
        `);

        expect(el.shadowRoot!.querySelector('.sluggi-hidden-page-note')).to.exist;
        expect(el.shadowRoot!.querySelector('.sluggi-redirect-info')).to.equal(null);
    });
});
