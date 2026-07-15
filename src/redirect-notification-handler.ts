import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
import ImmediateAction from '@typo3/backend/action-button/immediate-action.js';

const STORAGE_KEY = 'sluggi-redirect-choice';
const DEDUP_KEY = '__sluggiRecentReportSignatures';

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
    title: string;
    correlations: SlugReportCorrelations;
}

interface CascadeRoot {
    pageId: number;
    title: string;
    correlations: SlugReportCorrelations;
}

interface SlugChangeReportDetail {
    entries: SlugReportEntry[];
    pagesUpdated: number;
    redirectsCreated: number;
    cascadeRoot?: CascadeRoot;
}

interface RevertResponse {
    status: 'ok' | 'error';
    title: string;
    message: string;
}

class RedirectNotificationHandler {
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

        // Dedup across realms: the handler module is loaded in both the parent
        // backend frame and any iframe, and each instance registers a listener
        // on window.top.document. Without a shared dedup bag, both instances
        // would fire on a single dispatch.
        const dedupBag = this.getDedupBag();
        const entrySignature = detail.entries.map((entry) => entry.pageId).join(',');
        const cascadeSignature = detail.cascadeRoot ? `cr${detail.cascadeRoot.pageId}` : '';
        const signature = `${detail.pagesUpdated}:${detail.redirectsCreated}:${entrySignature}:${cascadeSignature}`;
        if (dedupBag.has(signature)) {
            return;
        }
        dedupBag.add(signature);
        setTimeout(() => dedupBag.delete(signature), 200);

        const { title, message } = this.buildTitleAndMessage(detail);
        const actions = this.buildActions(detail);
        Notification.info(title, message, 0, actions);
    }

    private getDedupBag(): Set<string> {
        // Duck-type the Set rather than use `instanceof Set` — when this
        // handler is loaded in two same-origin realms (parent backend +
        // iframe), each realm has its own Set constructor, and an
        // `instanceof` check against the other realm's Set returns false.
        const host = (window.top ?? window) as unknown as Record<string, unknown>;
        const existing = host[DEDUP_KEY] as { add?: unknown; has?: unknown; delete?: unknown } | undefined;
        if (existing && typeof existing.add === 'function' && typeof existing.has === 'function' && typeof existing.delete === 'function') {
            return existing as unknown as Set<string>;
        }
        const bag = new Set<string>();
        try {
            host[DEDUP_KEY] = bag;
        } catch {
            // Cross-origin top window: fall back to per-instance.
        }
        return bag;
    }

    private static derivePageUpdateCorrelationId(correlationIdSlugUpdate: string): string {
        const slashIndex = correlationIdSlugUpdate.indexOf('/');
        return slashIndex === -1 ? correlationIdSlugUpdate : correlationIdSlugUpdate.substring(0, slashIndex);
    }

    private buildTitleAndMessage(detail: SlugChangeReportDetail): { title: string; message: string } {
        const lang = TYPO3.lang;

        if (detail.cascadeRoot) {
            const titleKey = 'notification.slugReport.cascadeRoot.title';
            const messageKey = detail.pagesUpdated === 1
                ? 'notification.slugReport.cascadeRoot.singular'
                : 'notification.slugReport.cascadeRoot.plural';
            const message = (lang[messageKey] ?? '')
                .replace('{count}', String(detail.pagesUpdated))
                .replace('{title}', detail.cascadeRoot.title)
                .replace('{uid}', String(detail.cascadeRoot.pageId));
            return { title: lang[titleKey] ?? '', message };
        }

        const single = detail.entries.length <= 1;
        const hasRedirects = detail.redirectsCreated >= 1;
        const descendantCount = Math.max(0, detail.pagesUpdated - detail.entries.length);
        const descendantSuffix = descendantCount === 0
            ? ''
            : ' ' + (descendantCount === 1
                ? lang['notification.slugReport.descendantSuffix.singular'] ?? ''
                : (lang['notification.slugReport.descendantSuffix.plural'] ?? '').replace('{count}', String(descendantCount)));

        if (single && !hasRedirects) {
            const entry = detail.entries[0];
            const message = entry
                ? (lang['notification.slugReport.singleSlug.message'] ?? '')
                    .replace('{title}', entry.title)
                    .replace('{uid}', String(entry.pageId)) + descendantSuffix
                : lang['notification.slugReport.singleSlug.message'] ?? '';
            return {
                title: lang['notification.slugReport.singleSlug.title'] ?? '',
                message,
            };
        }

        if (single && hasRedirects) {
            const entry = detail.entries[0];
            const message = entry
                ? (lang['notification.slugReport.singleSlugAndRedirect.message'] ?? '')
                    .replace('{title}', entry.title)
                    .replace('{uid}', String(entry.pageId)) + descendantSuffix
                : lang['notification.slugReport.singleSlugAndRedirect.message'] ?? '';
            return {
                title: lang['notification.slugReport.singleSlugAndRedirect.title'] ?? '',
                message,
            };
        }

        // Multiple directly-edited entries — list them.
        const entryLineTemplate = lang['notification.slugReport.entryLine'] ?? '"{title}" (UID {uid})';
        const list = detail.entries
            .map((entry) => entryLineTemplate.replace('{title}', entry.title).replace('{uid}', String(entry.pageId)))
            .join(', ');

        if (!hasRedirects) {
            const baseMessage = (lang['notification.slugReport.multipleSlugs.message'] ?? '').replace('{list}', list);
            return {
                title: (lang['notification.slugReport.multipleSlugs.title'] ?? '').replace('%d', String(detail.pagesUpdated)),
                message: baseMessage + descendantSuffix,
            };
        }

        const titleKey = detail.redirectsCreated === 1
            ? 'notification.slugReport.multipleSlugsAndOneRedirect.title'
            : 'notification.slugReport.multipleSlugsAndRedirects.title';
        const messageKey = detail.redirectsCreated === 1
            ? 'notification.slugReport.multipleSlugsAndOneRedirect.message'
            : 'notification.slugReport.multipleSlugsAndRedirects.message';
        const baseMessage = (lang[messageKey] ?? '')
            .replace('{list}', list)
            .replace('{redirects}', String(detail.redirectsCreated));
        return {
            title: (lang[titleKey] ?? '')
                .replace('{pages}', String(detail.pagesUpdated))
                .replace('{redirects}', String(detail.redirectsCreated)),
            message: baseMessage + descendantSuffix,
        };
    }

    private buildActions(detail: SlugChangeReportDetail): Array<{ label: string; action: ImmediateAction }> {
        const actions: Array<{ label: string; action: ImmediateAction }> = [];
        const slugCorrelations: string[] = [];
        const redirectCorrelations: string[] = [];

        const sources: Array<{ correlations: SlugReportCorrelations }> = detail.cascadeRoot
            ? [{ correlations: detail.cascadeRoot.correlations }]
            : detail.entries;
        for (const source of sources) {
            const pageUpdate = RedirectNotificationHandler.derivePageUpdateCorrelationId(source.correlations.correlationIdSlugUpdate);
            slugCorrelations.push(pageUpdate, source.correlations.correlationIdSlugUpdate, source.correlations.correlationIdRedirectCreation);
            redirectCorrelations.push(source.correlations.correlationIdRedirectCreation);
        }

        if (slugCorrelations.length > 0) {
            actions.push({
                label: TYPO3.lang['notification.redirects.button.revert_update'],
                action: new ImmediateAction(async () => {
                    await this.revert(slugCorrelations, true);
                }),
            });
        }

        if (detail.redirectsCreated >= 1 && redirectCorrelations.length > 0) {
            actions.push({
                label: TYPO3.lang['notification.redirects.button.revert_redirect'],
                action: new ImmediateAction(async () => {
                    await this.revert(redirectCorrelations, false);
                }),
            });
        }

        return actions;
    }

    private async revert(correlationIds: string[], reloadForm: boolean): Promise<void> {
        let success = false;
        try {
            // TYPO3 13.4.33/14.3.5 made the revert endpoint POST-only and read
            // the ids from the request body; older cores accept any method and
            // read the query string. Send both so every core version works.
            const response = await new AjaxRequest(TYPO3.settings.ajaxUrls.redirects_revert_correlation)
                .withQueryArguments({ correlation_ids: correlationIds })
                .post({ correlation_ids: correlationIds });
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
        if (reloadForm && success) {
            // Refresh only the edit iframe so the form picks up the reverted
            // slug. Reloading window.top would destroy the success notification
            // we just queued.
            const iframe = document.querySelector('iframe');
            if (iframe?.contentWindow) {
                iframe.contentWindow.location.reload();
            }
        }
    }
}

export { RedirectNotificationHandler };
export default new RedirectNotificationHandler();
