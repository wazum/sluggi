declare const TYPO3: {
    settings: {
        ajaxUrls: Record<string, string>;
    };
    lang: Record<string, string>;
};

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

declare module '@typo3/backend/enum/severity.js' {
    export enum SeverityEnum {
        notice = -2,
        info = -1,
        ok = 0,
        warning = 1,
        error = 2,
    }
}

declare module '@typo3/backend/notification.js' {
    const Notification: {
        info(title: string, message: string, duration?: number, actions?: unknown[]): void;
        success(title: string, message: string, duration?: number, actions?: unknown[]): void;
        warning(title: string, message: string, duration?: number, actions?: unknown[]): void;
        error(title: string, message: string, duration?: number, actions?: unknown[]): void;
    };

    export default Notification;
}

declare module '@typo3/core/ajax/ajax-request.js' {
    interface AjaxResponse {
        resolve(): Promise<unknown>;
    }

    class AjaxRequest {
        constructor(url: string);
        withQueryArguments(params: Record<string, unknown>): this;
        get(): Promise<AjaxResponse>;
        post(data: Record<string, unknown>): Promise<AjaxResponse>;
    }

    export default AjaxRequest;
}

declare module '@typo3/backend/action-button/deferred-action.js' {
    class DeferredAction {
        constructor(callback: () => Promise<void>);
    }

    export default DeferredAction;
}
