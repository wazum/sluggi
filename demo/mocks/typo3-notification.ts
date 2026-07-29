type Severity = 'info' | 'warning' | 'error' | 'success' | 'notice';

function show(severity: Severity, title: string, message: string, duration = 5): void {
    let container = document.querySelector('.sluggi-demo-toasts');
    if (container === null) {
        container = document.createElement('div');
        container.className = 'sluggi-demo-toasts';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `sluggi-demo-toast sluggi-demo-toast--${severity}`;
    toast.setAttribute('role', 'status');

    const titleEl = document.createElement('strong');
    titleEl.textContent = title;
    toast.appendChild(titleEl);

    const messageEl = document.createElement('p');
    messageEl.textContent = message;
    toast.appendChild(messageEl);

    container.appendChild(toast);
    window.setTimeout(() => toast.remove(), Math.max(duration, 1) * 1000);
}

const Notification = {
    info: (title: string, message: string, duration?: number) => show('info', title, message, duration),
    warning: (title: string, message: string, duration?: number) => show('warning', title, message, duration),
    error: (title: string, message: string, duration?: number) => show('error', title, message, duration),
    success: (title: string, message: string, duration?: number) => show('success', title, message, duration),
    notice: (title: string, message: string, duration?: number) => show('notice', title, message, duration),
};

export default Notification;
