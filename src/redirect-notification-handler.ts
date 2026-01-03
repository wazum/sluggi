const STORAGE_KEY = 'sluggi-redirect-choice';

interface RedirectChoice {
    pageId: string;
    createRedirects: boolean;
    timestamp: number;
}

interface SlugChangedEventDetail {
    autoCreateRedirects?: boolean;
}

class RedirectNotificationHandler {
    constructor() {
        document.addEventListener('typo3:redirects:slugChanged', (event) => this.onSlugChanged(event as CustomEvent<SlugChangedEventDetail>), true);
    }

    static storeChoice(pageId: string, createRedirects: boolean): void {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ pageId, createRedirects, timestamp: Date.now() }));
    }

    static getAndClearChoice(): RedirectChoice | null {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (!stored) return null;

        localStorage.removeItem(STORAGE_KEY);

        try {
            const data = JSON.parse(stored) as RedirectChoice;
            if (Date.now() - data.timestamp > 30000) return null;
            return data;
        } catch {
            return null;
        }
    }

    onSlugChanged(event: CustomEvent<SlugChangedEventDetail>): void {
        const choice = RedirectNotificationHandler.getAndClearChoice();
        if (choice && !choice.createRedirects && event.detail?.autoCreateRedirects) {
            event.detail.autoCreateRedirects = false;
        }
    }
}

export { RedirectNotificationHandler };
export default new RedirectNotificationHandler();
