declare module '@typo3/backend/modal.js' {
    interface ModalButton {
        text: string;
        active?: boolean;
        btnClass?: string;
        trigger: () => void;
    }

    const Modal: {
        confirm(title: string, message: string, severity: number, buttons: ModalButton[]): void;
        dismiss(): void;
    };

    export default Modal;
}

declare module '@typo3/backend/severity.js' {
    const Severity: {
        notice: number;
        info: number;
        ok: number;
        warning: number;
        error: number;
    };

    export default Severity;
}
