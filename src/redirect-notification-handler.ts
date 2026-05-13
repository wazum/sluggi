import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
import DeferredAction from '@typo3/backend/action-button/deferred-action.js';

const STORAGE_KEY = 'sluggi-redirect-choice';

interface RedirectChoice {
    pageId: string;
    createRedirects: boolean;
    timestamp: number;
}

interface SlugReportCorrelations {
    correlationIdSlugUpdate: string;
    correlationIdRedirectCreation: string;
}

interface SlugReportEntry {
    pageId: number;
    correlations: SlugReportCorrelations;
}

interface SlugChangeReportDetail {
    entries: SlugReportEntry[];
    pagesUpdated: number;
    redirectsCreated: number;
}

interface RevertResponse {
    status: 'ok' | 'error';
    title: string;
    message: string;
}

class RedirectNotificationHandler {
    private readonly recentReportSignatures = new Set<string>();

    constructor() {
        const suppress = (event: Event): void => {
            event.stopImmediatePropagation();
        };
        document.addEventListener('typo3:redirects:slugChanged', suppress, true);
        if (window.top && window.top.document !== document) {
            window.top.document.addEventListener('typo3:redirects:slugChanged', suppress, true);
        }

        const reportListener = (event: Event): void => {
            this.onSlugChangeReport(event as CustomEvent<SlugChangeReportDetail>);
        };
        document.addEventListener('typo3:sluggi:slugChangeReport', reportListener);
        if (window.top && window.top.document !== document) {
            window.top.document.addEventListener('typo3:sluggi:slugChangeReport', reportListener);
        }
    }

    static storeChoice(pageId: string, createRedirects: boolean): void {
        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({ pageId, createRedirects, timestamp: Date.now() }),
        );
    }

    static getAndClearChoice(): RedirectChoice | null {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (!stored) return null;

        localStorage.removeItem(STORAGE_KEY);

        try {
            const data = JSON.parse(stored) as RedirectChoice;
            if (Date.now() - data.timestamp > 30000) return null;
            return data;
        } catch (error) {
            console.warn('sluggi: malformed redirect choice in localStorage', error);
            return null;
        }
    }

    onSlugChangeReport(event: CustomEvent<SlugChangeReportDetail>): void {
        const detail = event.detail;
        if (!detail || detail.pagesUpdated <= 0) {
            return;
        }

        // Dedup identical reports (e.g. handler attached on both window.document
        // and window.top.document when running in an iframe).
        const signature = `${detail.pagesUpdated}:${detail.redirectsCreated}:${detail.entries.map((entry) => entry.pageId).join(',')}`;
        if (this.recentReportSignatures.has(signature)) {
            return;
        }
        this.recentReportSignatures.add(signature);
        setTimeout(() => this.recentReportSignatures.delete(signature), 200);

        const { title, message } = this.buildTitleAndMessage(detail);
        const actions = this.buildActions(detail);
        Notification.info(title, message, 0, actions);
    }

    private static derivePageUpdateCorrelationId(correlationIdSlugUpdate: string): string {
        const slashIndex = correlationIdSlugUpdate.indexOf('/');
        return slashIndex === -1 ? correlationIdSlugUpdate : correlationIdSlugUpdate.substring(0, slashIndex);
    }

    private buildTitleAndMessage(detail: SlugChangeReportDetail): { title: string; message: string } {
        const lang = TYPO3.lang;
        const single = detail.pagesUpdated === 1;
        const hasRedirects = detail.redirectsCreated >= 1;

        if (single && !hasRedirects) {
            return {
                title: lang['notification.slugReport.singleSlug.title'],
                message: lang['notification.slugReport.singleSlug.message'],
            };
        }
        if (single && hasRedirects) {
            return {
                title: lang['notification.slugReport.singleSlugAndRedirect.title'],
                message: lang['notification.slugReport.singleSlugAndRedirect.message'],
            };
        }
        if (!single && !hasRedirects) {
            return {
                title: lang['notification.slugReport.multipleSlugs.title'].replace('%d', String(detail.pagesUpdated)),
                message: lang['notification.slugReport.multipleSlugs.message'].replace('%d', String(detail.pagesUpdated)),
            };
        }
        return {
            title: lang['notification.slugReport.multipleSlugsAndRedirects.title']
                .replace('{pages}', String(detail.pagesUpdated))
                .replace('{redirects}', String(detail.redirectsCreated)),
            message: lang['notification.slugReport.multipleSlugsAndRedirects.message']
                .replace('{pages}', String(detail.pagesUpdated))
                .replace('{redirects}', String(detail.redirectsCreated)),
        };
    }

    private buildActions(detail: SlugChangeReportDetail): Array<{ label: string; action: DeferredAction }> {
        const actions: Array<{ label: string; action: DeferredAction }> = [];
        const slugCorrelations: string[] = [];
        const redirectCorrelations: string[] = [];

        for (const entry of detail.entries) {
            const pageUpdate = RedirectNotificationHandler.derivePageUpdateCorrelationId(entry.correlations.correlationIdSlugUpdate);
            slugCorrelations.push(pageUpdate, entry.correlations.correlationIdSlugUpdate, entry.correlations.correlationIdRedirectCreation);
            redirectCorrelations.push(entry.correlations.correlationIdRedirectCreation);
        }

        actions.push({
            label: TYPO3.lang['notification.redirects.button.revert_update'],
            action: new DeferredAction(async () => {
                await this.revert(slugCorrelations, true);
            }),
        });

        if (detail.redirectsCreated >= 1) {
            actions.push({
                label: TYPO3.lang['notification.redirects.button.revert_redirect'],
                action: new DeferredAction(async () => {
                    await this.revert(redirectCorrelations, false);
                }),
            });
        }

        return actions;
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
