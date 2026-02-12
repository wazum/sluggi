import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
import DeferredAction from '@typo3/backend/action-button/deferred-action.js';

const STORAGE_KEY = 'sluggi-redirect-choice';

interface RedirectChoice {
    pageId: string;
    createRedirects: boolean;
    timestamp: number;
}

interface SlugChangedCorrelations {
    correlationIdSlugUpdate: string;
    correlationIdRedirectCreation: string;
}

interface SlugChangedEventDetail {
    autoCreateRedirects?: boolean;
    autoUpdateSlugs?: boolean;
    correlations?: SlugChangedCorrelations;
}

interface RevertResponse {
    status: 'ok' | 'error';
    title: string;
    message: string;
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

    private static derivePageUpdateCorrelationId(correlationIdSlugUpdate: string): string {
        const slashIndex = correlationIdSlugUpdate.indexOf('/');
        return slashIndex === -1 ? correlationIdSlugUpdate : correlationIdSlugUpdate.substring(0, slashIndex);
    }

    onSlugChanged(event: CustomEvent<SlugChangedEventDetail>): void {
        const choice = RedirectNotificationHandler.getAndClearChoice();
        if (choice && !choice.createRedirects && event.detail?.autoCreateRedirects) {
            event.detail.autoCreateRedirects = false;
        }

        const detail = event.detail;
        if (!detail?.correlations) return;

        event.stopImmediatePropagation();

        const correlations = detail.correlations;
        const correlationIdPageUpdate = RedirectNotificationHandler.derivePageUpdateCorrelationId(correlations.correlationIdSlugUpdate);
        const actions: Array<{ label: string; action: DeferredAction }> = [];

        if (detail.autoUpdateSlugs) {
            actions.push({
                label: TYPO3.lang['notification.redirects.button.revert_update'],
                action: new DeferredAction(async () => {
                    await this.revert([
                        correlationIdPageUpdate,
                        correlations.correlationIdSlugUpdate,
                        correlations.correlationIdRedirectCreation,
                    ], true);
                }),
            });
        }

        if (detail.autoCreateRedirects) {
            actions.push({
                label: TYPO3.lang['notification.redirects.button.revert_redirect'],
                action: new DeferredAction(async () => {
                    await this.revert([correlations.correlationIdRedirectCreation], false);
                }),
            });
        }

        let title = TYPO3.lang['notification.slug_only.title'];
        let message = TYPO3.lang['notification.slug_only.message'];

        if (detail.autoCreateRedirects) {
            title = TYPO3.lang['notification.slug_and_redirects.title'];
            message = TYPO3.lang['notification.slug_and_redirects.message'];
        }

        Notification.info(title, message, 0, actions);
    }

    private async revert(correlationIds: string[], reloadPage: boolean): Promise<void> {
        let success = false;
        try {
            const response = await new AjaxRequest(TYPO3.settings.ajaxUrls.redirects_revert_correlation)
                .withQueryArguments({ correlation_ids: correlationIds })
                .get();
            const result = await response.resolve() as RevertResponse;
            if (result.status === 'ok') {
                Notification.success(result.title, result.message);
                success = true;
            } else {
                Notification.error(result.title, result.message);
            }
        } catch {
            Notification.error(
                TYPO3.lang.redirects_error_title,
                TYPO3.lang.redirects_error_message,
            );
        }
        document.dispatchEvent(new CustomEvent('typo3:pagetree:refresh'));
        if (reloadPage && success) {
            window.location.reload();
        }
    }
}

export { RedirectNotificationHandler };
export default new RedirectNotificationHandler();
