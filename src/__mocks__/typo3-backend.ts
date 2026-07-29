interface ModalButton {
    text: string;
    btnClass?: string;
    active?: boolean;
    trigger?: () => void;
}

const calls: Array<{ title: string; message: string; severity: number; buttons: ModalButton[]; modal: HTMLElement }> = [];

export const Modal = {
    confirm: (title: string, message: string, severity: number, buttons: ModalButton[] = []) => {
        // A real modal is an EventTarget — callers listen for typo3-modal-hidden
        // to notice a dismissal that bypassed every button.
        const modal = document.createElement('div');
        calls.push({ title, message, severity, buttons, modal });

        return modal;
    },
    dismiss: () => {},
    _calls: calls,
    _reset: () => { calls.length = 0; },
};

export const Severity = {
    notice: 0,
    info: 1,
    ok: 2,
    warning: 3,
    error: 4,
};

export default Modal;
