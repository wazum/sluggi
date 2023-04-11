import DocumentService from "@typo3/core/document-service";
import RegularEvent from '@typo3/core/event/regular-event';

declare global {
    interface Window {
        TYPO3: string[][];
        tx_sluggi_sync: boolean;
        tx_sluggi_lock: boolean;
    }
}

enum Selectors {
    toggleButton = '.t3js-form-field-slug-toggle',
    recreateButton = '.t3js-form-field-slug-recreate',
    inputField = '.t3js-form-field-slug-input',
    readOnlyField = '.t3js-form-field-slug-readonly',
    hiddenField = '.t3js-form-field-slug-hidden',
    synchronizationToggleButton = 'input[data-formengine-input-name$="[tx_sluggi_sync]"]',
    lockToggleButton = 'input[data-formengine-input-name$="[slug_locked]"]',
}

class Sluggi {
    private readonly slugWrapperElement: HTMLElement = null;

    constructor() {
        this.slugWrapperElement = document.querySelector('div[id^="t3js-form-field-slug-id"]');
        DocumentService.ready().then((document: Document): void => {
            this.setDefaults();
            this.registerEvents();
        });
    }

    private setDefaults (): void {
        if (this.synchronizationActive()) {
            this.disableSlugEditingFunctions('Synchronization active');
        } else if (this.lockActive()) {
            this.disableSlugEditingFunctions('Lock active');
        }
    }

    private registerEvents (): void {
        this.registerSynchronizationToggleEvents();
        this.registerLockToggleEvents();
        this.registerSlugImpactBlurEvents();
        this.registerInputDoubleClickEvents();
    }

    private enableSlugEditingFunctions (): void {
        const toggleButton: HTMLElement = this.slugWrapperElement.querySelector(Selectors.toggleButton);
        const recreateButton: HTMLElement = this.slugWrapperElement.querySelector(Selectors.recreateButton);
        toggleButton.style.display = 'inline-flex';
        recreateButton.style.visibility = 'visible';
        recreateButton.style.position = 'relative';
        recreateButton.parentElement.removeAttribute('title');
    };

    private disableSlugEditingFunctions (title: string): void {
        const toggleButton: HTMLElement = this.slugWrapperElement.querySelector(Selectors.toggleButton);
        const recreateButton: HTMLElement = this.slugWrapperElement.querySelector(Selectors.recreateButton);
        toggleButton.style.display = 'none';
        recreateButton.style.visibility = 'hidden';
        recreateButton.style.position = 'absolute';
        recreateButton.parentElement.setAttribute('title', title);
    };

    private registerSynchronizationToggleEvents (): void {
        const synchronizationToggleButton: HTMLElement = document.querySelector(Selectors.synchronizationToggleButton);
        const lockToggleButton: HTMLElement = document.querySelector(Selectors.lockToggleButton);
        const inputField: HTMLInputElement = this.slugWrapperElement.querySelector(Selectors.inputField);
        if (synchronizationToggleButton) {
            new RegularEvent('click', (e: Event): void => {
                const synchronizationToggleValue: HTMLInputElement = document.querySelector(
                    'input[name="' + synchronizationToggleButton.getAttribute('data-formengine-input-name') + '"]'
                );
                // The value on click is '1' so the toggle is switched to off
                if ('1' === synchronizationToggleValue.value) {
                    if (!this.lockActive()) {
                        this.enableSlugEditingFunctions();
                    }
                    inputField.dataset.txSluggiSync = '0';
                } else {
                    this.triggerSlugProposal();
                    inputField.dataset.txSluggiSync = '1';
                    if (this.lockActive()) {
                        lockToggleButton.click();
                    }
                    window.setTimeout(() => {
                        this.disableSlugEditingFunctions('Synchronization active');
                    }, 100);
                }
            }).bindTo(synchronizationToggleButton);
        }
    }

    private registerLockToggleEvents (): void {
        const lockToggleButton: HTMLElement = document.querySelector(Selectors.lockToggleButton);
        const synchronizationToggleButton: HTMLElement = document.querySelector(Selectors.synchronizationToggleButton);
        const inputField: HTMLInputElement = this.slugWrapperElement.querySelector(Selectors.inputField);
        if (lockToggleButton) {
            new RegularEvent('click', (e: Event): void => {
                const lockToggleValue: HTMLInputElement = document.querySelector(
                    'input[name="' + lockToggleButton.getAttribute('data-formengine-input-name') + '"]'
                );
                // The value on click is '1' so the toggle is switched to off
                if ('1' === lockToggleValue.value) {
                    if (!this.synchronizationActive()) {
                        this.enableSlugEditingFunctions();
                    }
                    inputField.dataset.txSluggiLock = '0';
                } else {
                    inputField.dataset.txSluggiLock = '1';
                    if (this.synchronizationActive()) {
                        synchronizationToggleButton.click();
                    }
                    window.setTimeout(() => {
                        this.disableSlugEditingFunctions('Lock active');
                    }, 100);
                }
            }).bindTo(lockToggleButton);
        }
    }

    private registerSlugImpactBlurEvents (): void {
        document.querySelectorAll('.slug-impact').forEach((element) => {
            new RegularEvent('blur', (e: Event): void => {
                if (this.synchronizationActive()) {
                    this.triggerSlugProposal();
                }
            }).bindTo(element);
        });
    }

    private registerInputDoubleClickEvents (): void {
        const readOnlyField: HTMLElement = this.slugWrapperElement.querySelector(Selectors.readOnlyField);
        const inputField: HTMLElement = this.slugWrapperElement.querySelector(Selectors.inputField);
        readOnlyField.addEventListener('dblclick', () => {
            if (!this.synchronizationActive() && !this.lockActive()) {
                const toggleButton = this.slugWrapperElement.querySelector<HTMLElement>(Selectors.toggleButton);
                const handleBlur = () => {
                    toggleButton.click();
                    inputField.removeEventListener('blur', handleBlur);
                };
                inputField.addEventListener('blur', handleBlur);
                toggleButton.click();
            }
        });
    }

    private triggerSlugProposal (): void {
        const recreateButton: HTMLElement = this.slugWrapperElement.querySelector(Selectors.recreateButton);
        recreateButton.click();
    }

    private synchronizationActive (): boolean {
        const inputField: HTMLInputElement = this.slugWrapperElement.querySelector(Selectors.inputField);
        let synchronizationActive = false;
        if (parseInt(inputField.dataset.txSluggiSync)) {
            synchronizationActive = true;
        }

        return synchronizationActive;
    }

    private lockActive (): boolean {
        const inputField: HTMLInputElement = this.slugWrapperElement.querySelector(Selectors.inputField);
        let lockActive = false;
        if (parseInt(inputField.dataset.txSluggiLock)) {
            lockActive = true;
        }

        return lockActive;
    }
}

const sluggi = new Sluggi();
export default sluggi;
