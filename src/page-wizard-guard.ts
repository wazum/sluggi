/**
 * Forces FormEngine visible→hidden sync on the currently focused input when
 * a page-wizard button is pressed. The v14 wizard reads FormData (hidden
 * mirrors only) and filters out empty values; without this, clicking "Next"
 * before the focused field's `change`/blur has propagated drops its value.
 *
 * Only the focused field can have a stale mirror — all other fields were
 * synced when focus moved away — so we leave them alone to avoid firing
 * unrelated onChange handlers.
 */
class PageWizardGuard {
    constructor() {
        document.addEventListener('pointerdown', this.handlePointerDown, true);
    }

    private readonly handlePointerDown = (event: PointerEvent): void => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const wizard = target.closest('typo3-backend-page-wizard');
        if (!wizard || !target.closest('button')) {
            return;
        }
        const form = wizard.querySelector<HTMLFormElement>('form[name="editform"]');
        if (!form) {
            return;
        }
        const focused = document.activeElement;
        if (
            !(focused instanceof HTMLInputElement
                || focused instanceof HTMLTextAreaElement
                || focused instanceof HTMLSelectElement)
            || !form.contains(focused)
            || !focused.hasAttribute('data-formengine-input-name')
            || (focused instanceof HTMLInputElement && focused.type === 'hidden')
        ) {
            return;
        }
        focused.dispatchEvent(new Event('change', { bubbles: true }));
    };
}

export { PageWizardGuard };
export default new PageWizardGuard();
