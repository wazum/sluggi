import { expect } from '@open-wc/testing';
import '../page-wizard-guard.js';

function createField(
    parent: HTMLElement,
    fieldName: string,
): { visible: HTMLInputElement; hidden: HTMLInputElement } {
    const visible = document.createElement('input');
    visible.type = 'text';
    visible.setAttribute('data-formengine-input-name', fieldName);
    visible.value = '';
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.setAttribute('name', fieldName);
    hidden.setAttribute('data-formengine-input-name', fieldName);
    hidden.value = '';
    parent.append(visible, hidden);

    // Mirror FormEngine's visible→hidden sync on change.
    visible.addEventListener('change', () => {
        hidden.value = visible.value;
    });

    return { visible, hidden };
}

describe('PageWizardGuard', () => {
    let wizardHost: HTMLElement;
    let form: HTMLFormElement;
    let nextButton: HTMLButtonElement;

    beforeEach(() => {
        wizardHost = document.createElement('typo3-backend-page-wizard');
        form = document.createElement('form');
        form.setAttribute('name', 'editform');
        nextButton = document.createElement('button');
        nextButton.type = 'button';
        nextButton.classList.add('btn-primary');
        nextButton.textContent = 'Next';
        wizardHost.append(form, nextButton);
        document.body.appendChild(wizardHost);
    });

    afterEach(() => {
        document.body.removeChild(wizardHost);
    });

    it('syncs the focused FormEngine input on wizard button pointerdown', () => {
        const titleField = createField(form, 'data[pages][NEW1][title]');

        titleField.visible.focus();
        titleField.visible.value = 'Focused Sync Test';
        expect(titleField.hidden.value).to.equal('');

        nextButton.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));

        expect(titleField.hidden.value).to.equal('Focused Sync Test');
    });

    it('does not touch unfocused FormEngine inputs', () => {
        const titleField = createField(form, 'data[pages][NEW1][title]');
        const navTitleField = createField(form, 'data[pages][NEW1][nav_title]');

        titleField.visible.focus();
        titleField.visible.value = 'Title';
        // Stale state on an unfocused field — should not be touched.
        navTitleField.visible.value = 'Stale Nav';

        nextButton.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));

        expect(titleField.hidden.value).to.equal('Title');
        expect(navTitleField.hidden.value).to.equal('');
    });

    it('does nothing when no FormEngine input is focused', () => {
        const titleField = createField(form, 'data[pages][NEW1][title]');
        titleField.visible.value = 'No Focus';

        nextButton.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));

        expect(titleField.hidden.value).to.equal('');
    });

    it('ignores pointerdown outside a page-wizard', () => {
        const orphanHost = document.createElement('div');
        const orphanForm = document.createElement('form');
        orphanForm.setAttribute('name', 'editform');
        const orphanButton = document.createElement('button');
        orphanButton.textContent = 'Next';
        orphanHost.append(orphanForm, orphanButton);
        document.body.appendChild(orphanHost);

        const titleField = createField(orphanForm, 'data[pages][NEW2][title]');
        titleField.visible.focus();
        titleField.visible.value = 'Should Not Sync';

        orphanButton.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));

        expect(titleField.hidden.value).to.equal('');

        document.body.removeChild(orphanHost);
    });
});
