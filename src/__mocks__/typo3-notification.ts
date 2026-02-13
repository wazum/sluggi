const calls: Array<{ type: string; title: string; message: string; duration?: number }> = [];

const Notification = {
    info: (title: string, message: string, duration?: number) => calls.push({ type: 'info', title, message, duration }),
    warning: (title: string, message: string, duration?: number) => calls.push({ type: 'warning', title, message, duration }),
    _calls: calls,
    _reset: () => { calls.length = 0; },
};

export default Notification;
