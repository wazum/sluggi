/**
 * TYPO3 12 compatibility for holding a save back.
 *
 * TYPO3 13+ saves the edit form through a real submit event, which can be
 * canceled and repeated later. TYPO3 12 saves through jQuery's
 * trigger('submit'), whose default action calls the native form.submit():
 * no submit event is dispatched and preventDefault has nothing to cancel.
 * The click on the save button is the only place left to hold the save, and
 * repeating that click is what resumes it.
 *
 * @deprecated Remove this file when dropping TYPO3 12 support
 */

export interface LegacySave {
    form: HTMLFormElement;
    resumeSave: () => void;
}

export function findLegacySave(event: MouseEvent): LegacySave | null {
    const target = event.target as HTMLElement | null;
    // Covers every FormEngine save submitter (_savedok, _saveandclosedok, …)
    const saveButton = target?.closest('button[name^="_save"]') as HTMLButtonElement | null;
    if (!saveButton) {
        return null;
    }

    const form = findAssociatedForm(saveButton);
    if (!form) {
        return null;
    }

    return { form, resumeSave: () => saveButton.click() };
}

function findAssociatedForm(button: HTMLButtonElement): HTMLFormElement | null {
    const formId = button.getAttribute('form');
    if (formId) {
        return button.ownerDocument.getElementById(formId) as HTMLFormElement | null;
    }

    return button.closest('form');
}
