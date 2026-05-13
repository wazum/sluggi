const calls: Array<{ type: string; title: string; message: string; duration?: number; actions?: unknown[] }> = [];

const Notification = {
    info: (title: string, message: string, duration?: number, actions?: unknown[]) =>
        calls.push({ type: 'info', title, message, duration, actions }),
    warning: (title: string, message: string, duration?: number, actions?: unknown[]) =>
        calls.push({ type: 'warning', title, message, duration, actions }),
    success: (title: string, message: string, duration?: number, actions?: unknown[]) =>
        calls.push({ type: 'success', title, message, duration, actions }),
    error: (title: string, message: string, duration?: number, actions?: unknown[]) =>
        calls.push({ type: 'error', title, message, duration, actions }),
    _calls: calls,
    _reset: () => { calls.length = 0; },
};

export default Notification;
