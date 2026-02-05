interface ModalButton {
    text: string;
    btnClass?: string;
    active?: boolean;
    trigger?: () => void;
}

let currentDialog: HTMLDialogElement | null = null;

const Modal = {
    confirm(title: string, message: string, _severity: number, buttons: ModalButton[]) {
        Modal.dismiss();

        const dialog = document.createElement('dialog');
        dialog.className = 'sluggi-demo-modal';

        const titleEl = document.createElement('h3');
        titleEl.textContent = title;
        dialog.appendChild(titleEl);

        const messageEl = document.createElement('p');
        messageEl.textContent = message;
        messageEl.style.whiteSpace = 'pre-line';
        dialog.appendChild(messageEl);

        const actionsEl = document.createElement('div');
        actionsEl.className = 'sluggi-demo-modal-actions';

        for (const button of buttons) {
            const btn = document.createElement('button');
            btn.textContent = button.text;
            btn.type = 'button';

            if (button.btnClass?.includes('btn-warning')) {
                btn.className = 'warning';
            } else if (button.btnClass?.includes('btn-primary')) {
                btn.className = 'primary';
            }

            if (button.active) {
                btn.autofocus = true;
            }

            btn.addEventListener('click', () => {
                button.trigger?.();
            });
            actionsEl.appendChild(btn);
        }

        dialog.appendChild(actionsEl);

        dialog.addEventListener('cancel', () => {
            Modal.dismiss();
        });

        document.body.appendChild(dialog);
        dialog.showModal();
        currentDialog = dialog;
    },

    dismiss() {
        if (currentDialog) {
            currentDialog.close();
            currentDialog.remove();
            currentDialog = null;
        }
    },
};

export default Modal;
