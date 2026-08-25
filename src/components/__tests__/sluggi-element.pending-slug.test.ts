import '../sluggi-element.js';
import { fixture, html, expect } from './helpers.js';
import type { SluggiElement } from './helpers.js';
import Modal from '../../__mocks__/typo3-backend.js';

const pendingNoteText = 'The URL is generated from the page title when you save. Afterwards it is locked, like the original language version.';
const lockNoteText = 'The URL path is locked and cannot be edited.';

const labels = JSON.stringify({
    lockRestrictionNote: lockNoteText,
    'restriction.slugPending': pendingNoteText,
});

const chainMetadata = JSON.stringify({
    nav_title: { slot: 1, role: 'preferred', chainSize: 2 },
    title: { slot: 1, role: 'fallback', chainSize: 2 },
    subtitle: { slot: 2, role: 'single', chainSize: 1 },
});

function buildPendingElement(values: Record<string, string>): { container: HTMLDivElement; element: SluggiElement } {
    const container = document.createElement('div');
    for (const [fieldName, value] of Object.entries(values)) {
        const input = document.createElement('input');
        input.setAttribute('data-formengine-input-name', `data[pages][7][${fieldName}]`);
        input.value = value;
        container.appendChild(input);
    }

    const element = document.createElement('sluggi-element') as SluggiElement;
    element.setAttribute('value', '/parent/translate-to-german-page');
    element.setAttribute('table-name', 'pages');
    element.setAttribute('record-id', '7');
    element.setAttribute('page-id', '7');
    element.setAttribute('is-locked', '');
    element.setAttribute('is-translation', '');
    element.setAttribute('slug-pending', '');
    element.setAttribute('required-source-fields', 'title,subtitle');
    element.setAttribute('source-fields', chainMetadata);
    container.appendChild(element);
    document.body.appendChild(container);

    return { container, element };
}

function sourceInput(container: HTMLElement, fieldName: string): HTMLInputElement {
    return container.querySelector(`[data-formengine-input-name="data[pages][7][${fieldName}]"]`) as HTMLInputElement;
}

describe('SluggiElement - pending slug proposals', () => {
    it('requests a proposal when a first-slot source field changes', async () => {
        const { container, element } = buildPendingElement({ nav_title: '', title: 'Alter Titel', subtitle: 'Untertitel' });
        await element.updateComplete;

        let requested = false;
        element.addEventListener('sluggi-request-proposal', () => { requested = true; });

        const input = sourceInput(container, 'title');
        input.value = 'Neuer Titel';
        input.dispatchEvent(new Event('change', { bubbles: true }));
        await new Promise(resolve => setTimeout(resolve, 300));

        expect(requested, 'a first-slot change must update the preview').to.be.true;
        document.body.removeChild(container);
    });

    it('does not request a proposal when a later-slot source field changes', async () => {
        const { container, element } = buildPendingElement({ nav_title: '', title: 'Titel', subtitle: 'Untertitel' });
        await element.updateComplete;

        let requested = false;
        element.addEventListener('sluggi-request-proposal', () => { requested = true; });

        const input = sourceInput(container, 'subtitle');
        input.value = 'Anderer Untertitel';
        input.dispatchEvent(new Event('change', { bubbles: true }));
        await new Promise(resolve => setTimeout(resolve, 300));

        expect(requested, 'the server would not regenerate for a later slot either').to.be.false;
        document.body.removeChild(container);
    });

    it('requests a proposal when the preferred field is cleared and the fallback carries the value', async () => {
        const { container, element } = buildPendingElement({ nav_title: '[Translate to German:] Page', title: 'Neuer Titel', subtitle: 'Untertitel' });
        await element.updateComplete;

        let requested = false;
        element.addEventListener('sluggi-request-proposal', () => { requested = true; });

        const input = sourceInput(container, 'nav_title');
        input.value = '';
        input.dispatchEvent(new Event('change', { bubbles: true }));
        await new Promise(resolve => setTimeout(resolve, 300));

        expect(requested, 'clearing the preferred field makes the fallback effective').to.be.true;
        document.body.removeChild(container);
    });
});

const modalLabels = JSON.stringify({
    'pendingLockModal.title': 'URL path will be locked',
    'pendingLockModal.message': 'The URL path of this page will be set to %s and then locked.',
    'pendingLockModal.button.cancel': 'Check again',
    'pendingLockModal.button.confirm': 'Save and lock URL path',
});

interface PendingForm {
    container: HTMLDivElement;
    element: SluggiElement;
    saveButton: HTMLButtonElement;
    wasSubmitted: () => boolean;
}

async function buildPendingForm(elementAttributes: Record<string, string> = {}): Promise<PendingForm> {
    const container = document.createElement('div');
    const form = document.createElement('form');
    form.id = 'editform';
    let submitted = false;
    form.addEventListener('submit', event => {
        event.preventDefault();
        submitted = true;
    });

    const source = document.createElement('input');
    source.setAttribute('data-formengine-input-name', 'data[pages][7][title]');
    source.value = 'Neuer Titel';
    form.appendChild(source);

    const element = document.createElement('sluggi-element') as SluggiElement;
    const attributes: Record<string, string> = {
        value: '/parent/translate-to-german-page',
        'table-name': 'pages',
        'record-id': '7',
        'page-id': '7',
        'is-locked': '',
        'is-translation': '',
        'slug-pending': '',
        labels: modalLabels,
        ...elementAttributes,
    };
    for (const [name, value] of Object.entries(attributes)) {
        element.setAttribute(name, value);
    }
    form.appendChild(element);

    const saveButton = document.createElement('button');
    saveButton.setAttribute('name', '_savedok');
    saveButton.type = 'submit';
    form.appendChild(saveButton);

    container.appendChild(form);
    document.body.appendChild(container);
    await element.updateComplete;

    return { container, element, saveButton, wasSubmitted: () => submitted };
}

describe('SluggiElement - pending slug lock confirmation', () => {
    beforeEach(() => Modal._reset());

    it('asks for confirmation before saving a generated URL path, without redirect control', async () => {
        const { container, element, saveButton, wasSubmitted } = await buildPendingForm();
        element.value = '/parent/neuer-titel';
        await element.updateComplete;

        saveButton.click();

        expect(Modal._calls.length, 'the editor must be warned before the URL path is locked').to.equal(1);
        expect(Modal._calls[0].message).to.contain('/parent/neuer-titel');
        expect(wasSubmitted(), 'the save waits for the decision').to.be.false;
        document.body.removeChild(container);
    });

    it('saves after the confirmation is accepted', async () => {
        const { container, element, saveButton, wasSubmitted } = await buildPendingForm();
        element.value = '/parent/neuer-titel';
        await element.updateComplete;

        saveButton.click();
        const confirmButton = Modal._calls[0].buttons.find(button => button.btnClass === 'btn-primary');
        confirmButton?.trigger?.();

        expect(wasSubmitted(), 'accepting must save').to.be.true;
        document.body.removeChild(container);
    });

    it('does not save when the confirmation is cancelled', async () => {
        const { container, element, saveButton, wasSubmitted } = await buildPendingForm();
        element.value = '/parent/neuer-titel';
        await element.updateComplete;

        saveButton.click();
        const cancelButton = Modal._calls[0].buttons.find(button => button.btnClass === 'btn-default');
        cancelButton?.trigger?.();

        expect(wasSubmitted(), 'cancelling must not save').to.be.false;
        document.body.removeChild(container);
    });

    it('does not ask when the URL path was not changed', async () => {
        const { container, saveButton } = await buildPendingForm();

        saveButton.click();

        expect(Modal._calls.length, 'an untouched translation regenerates nothing').to.equal(0);
        document.body.removeChild(container);
    });

    it('never asks about redirects for a URL path that was only ever a placeholder', async () => {
        const { container, element, saveButton, wasSubmitted } = await buildPendingForm({ 'redirect-control': '' });
        element.value = '/parent/neuer-titel';
        await element.updateComplete;

        saveButton.click();
        expect(Modal._calls.length, 'only the lock confirmation belongs here').to.equal(1);

        Modal._calls[0].buttons.find(button => button.btnClass === 'btn-primary')?.trigger?.();

        expect(Modal._calls.length, 'a redirect from the placeholder path is meaningless').to.equal(1);
        expect(wasSubmitted(), 'confirming is the only decision needed').to.be.true;
        document.body.removeChild(container);
    });

    it('blocks the save while the URL preview could not be loaded', async () => {
        const originalFetch = window.fetch;
        const originalTypo3 = (window as unknown as { TYPO3?: unknown }).TYPO3;
        (window as unknown as { TYPO3: unknown }).TYPO3 = { settings: { ajaxUrls: { record_slug_suggest: '/fake-endpoint' } } };
        window.fetch = async () => new Response('', { status: 500 });

        const { container, element, saveButton, wasSubmitted } = await buildPendingForm({ 'field-name': 'slug' });
        await element.sendSlugProposal('recreate');

        saveButton.click();

        expect(Modal._calls.length, 'a URL path nobody could preview must not be confirmed').to.equal(0);
        expect(wasSubmitted(), 'the save must wait for a working preview').to.be.false;

        window.fetch = originalFetch;
        (window as unknown as { TYPO3?: unknown }).TYPO3 = originalTypo3;
        document.body.removeChild(container);
    });

    it('runs the lock confirmation before the redirect modal and submits once', async () => {
        const { container, element, saveButton, wasSubmitted } = await buildPendingForm();

        const redirectElement = document.createElement('sluggi-element') as SluggiElement;
        redirectElement.setAttribute('value', '/other-page');
        redirectElement.setAttribute('table-name', 'pages');
        redirectElement.setAttribute('record-id', '9');
        redirectElement.setAttribute('page-id', '9');
        redirectElement.setAttribute('redirect-control', '');
        redirectElement.setAttribute('labels', modalLabels);
        element.parentElement!.insertBefore(redirectElement, saveButton);
        await redirectElement.updateComplete;

        element.value = '/parent/neuer-titel';
        redirectElement.value = '/other-page-renamed';
        await element.updateComplete;
        await redirectElement.updateComplete;

        saveButton.click();

        expect(Modal._calls.length, 'the lock confirmation comes first').to.equal(1);
        expect(Modal._calls[0].title).to.equal('URL path will be locked');
        expect(wasSubmitted(), 'nothing is saved before both decisions').to.be.false;

        Modal._calls[0].buttons.find(button => button.btnClass === 'btn-primary')?.trigger?.();

        expect(Modal._calls.length, 'the redirect decision must still be asked').to.equal(2);
        expect(wasSubmitted(), 'the redirect modal owns the submit now').to.be.false;

        Modal._calls[1].buttons.find(button => button.btnClass === 'btn-primary')?.trigger?.();

        expect(wasSubmitted(), 'the form submits once both decisions are made').to.be.true;
        document.body.removeChild(container);
    });
});

describe('SluggiElement - pending slug generation', () => {
    it('replaces the lock note with the pending note', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/parent/translate-to-german-page"
                record-id="3"
                page-id="3"
                is-locked
                is-translation
                slug-pending
                labels="${labels}"
            ></sluggi-element>
        `);

        const note = el.shadowRoot!.querySelector('.sluggi-note');
        expect(note?.textContent?.trim()).to.equal(pendingNoteText);
    });

    it('keeps the lock note for a locked slug that is not pending', async () => {
        const el = await fixture<SluggiElement>(html`
            <sluggi-element
                value="/parent/page"
                record-id="3"
                page-id="3"
                is-locked
                is-translation
                labels="${labels}"
            ></sluggi-element>
        `);

        const note = el.shadowRoot!.querySelector('.sluggi-note');
        expect(note?.textContent?.trim()).to.equal(lockNoteText);
    });
});
