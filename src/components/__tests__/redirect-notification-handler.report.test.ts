import { expect } from '@open-wc/testing';

function setupTYPO3Global(): void {
    (window as unknown as Record<string, unknown>).TYPO3 = {
        settings: { ajaxUrls: { redirects_revert_correlation: '/api/revert' } },
        lang: {
            'notification.slugReport.singleSlug.title': 'URL path updated',
            'notification.slugReport.singleSlug.message': 'The URL path for page "{title}" (UID {uid}) was updated.',
            'notification.slugReport.singleSlugAndRedirect.title': 'URL path updated, redirect created',
            'notification.slugReport.singleSlugAndRedirect.message': 'The URL path for page "{title}" (UID {uid}) was updated and a redirect was created.',
            'notification.slugReport.multipleSlugs.title': '%d URL paths updated',
            'notification.slugReport.multipleSlugs.message': 'URL paths updated for: {list}.',
            'notification.slugReport.multipleSlugsAndOneRedirect.title': '{pages} URL paths updated, redirect created',
            'notification.slugReport.multipleSlugsAndOneRedirect.message': 'URL paths updated for: {list}. 1 redirect was created.',
            'notification.slugReport.multipleSlugsAndRedirects.title': '{pages} URL paths updated, {redirects} redirects created',
            'notification.slugReport.multipleSlugsAndRedirects.message': 'URL paths updated for: {list}. {redirects} redirects were created.',
            'notification.slugReport.entryLine': '"{title}" (UID {uid})',
            'notification.slugReport.descendantSuffix.singular': '1 child URL path was also updated.',
            'notification.slugReport.descendantSuffix.plural': '{count} child URL paths were also updated.',
            'notification.slugReport.cascadeRoot.title': 'URL paths regenerated',
            'notification.slugReport.cascadeRoot.singular': '1 URL path under "{title}" (UID {uid}) was regenerated.',
            'notification.slugReport.cascadeRoot.plural': '{count} URL paths under "{title}" (UID {uid}) were regenerated.',
            'notification.redirects.button.revert_update': 'Revert update',
            'notification.redirects.button.revert_redirect': 'Revert redirect',
            redirects_error_title: 'Error',
            redirects_error_message: 'Something went wrong.',
        },
    };
}

// TYPO3.lang must exist before the module-level singleton in
// redirect-notification-handler.ts is instantiated.
setupTYPO3Global();

// Side-effectful import: registers the singleton handler on document.
import '../../redirect-notification-handler.js';
import Notification from '../../__mocks__/typo3-notification.js';

interface NotificationCall {
    type: string;
    title: string;
    message: string;
    duration?: number;
    actions?: Array<{ label: string; action: unknown }>;
}

const notificationCalls = (Notification as unknown as { _calls: NotificationCall[] })._calls;
const resetNotifications = (Notification as unknown as { _reset: () => void })._reset;

function entry(pageId: number, title: string): { pageId: number; title: string; correlations: { correlationIdSlugUpdate: string; correlationIdRedirectCreation: string } } {
    return {
        pageId,
        title,
        correlations: {
            correlationIdSlugUpdate: `corr-${pageId}/slug`,
            correlationIdRedirectCreation: `corr-${pageId}/redirect`,
        },
    };
}

interface ReportDetail {
    pagesUpdated: number;
    redirectsCreated: number;
    entries?: Array<{ pageId: number; title: string; correlations: { correlationIdSlugUpdate: string; correlationIdRedirectCreation: string } }>;
    cascadeRoot?: { pageId: number; title: string; correlations: { correlationIdSlugUpdate: string; correlationIdRedirectCreation: string } };
}

function dispatchReport(detail: ReportDetail): void {
    document.dispatchEvent(
        new CustomEvent('typo3:sluggi:slugChangeReport', {
            detail: { entries: [], ...detail },
        }),
    );
}

describe('RedirectNotificationHandler - slugChangeReport rendering', () => {
    beforeEach(() => {
        resetNotifications();
        // Drain the cross-realm dedup bag between tests.
        const host = window as unknown as Record<string, Set<string>>;
        host.__sluggiRecentReportSignatures?.clear();
    });

    it('renders title + UID in message for a single direct edit, no redirect', () => {
        dispatchReport({
            pagesUpdated: 1,
            redirectsCreated: 0,
            entries: [entry(42, 'Conflict Test Page')],
        });

        const infoCalls = notificationCalls.filter((call) => call.type === 'info');
        expect(infoCalls).to.have.lengthOf(1);
        expect(infoCalls[0].title).to.equal('URL path updated');
        expect(infoCalls[0].message).to.equal('The URL path for page "Conflict Test Page" (UID 42) was updated.');
        const revertRedirect = (infoCalls[0].actions ?? []).find((action) => action.label === 'Revert redirect');
        expect(revertRedirect, 'Revert redirect button must be absent when no redirects were created').to.be.undefined;
    });

    it('renders title + UID in message for single direct edit with redirect', () => {
        dispatchReport({
            pagesUpdated: 1,
            redirectsCreated: 1,
            entries: [entry(42, 'Conflict Test Page')],
        });

        const infoCalls = notificationCalls.filter((call) => call.type === 'info');
        expect(infoCalls).to.have.lengthOf(1);
        expect(infoCalls[0].title).to.equal('URL path updated, redirect created');
        expect(infoCalls[0].message).to.equal('The URL path for page "Conflict Test Page" (UID 42) was updated and a redirect was created.');
        const revertRedirect = (infoCalls[0].actions ?? []).find((action) => action.label === 'Revert redirect');
        expect(revertRedirect).to.not.be.undefined;
    });

    it('appends descendant suffix when cascade descendants were updated alongside the direct edit', () => {
        dispatchReport({
            pagesUpdated: 4,
            redirectsCreated: 4,
            entries: [entry(45, 'Multi Edit Parent')],
        });

        const infoCalls = notificationCalls.filter((call) => call.type === 'info');
        expect(infoCalls).to.have.lengthOf(1);
        expect(infoCalls[0].title).to.equal('URL path updated, redirect created');
        expect(infoCalls[0].message).to.equal('The URL path for page "Multi Edit Parent" (UID 45) was updated and a redirect was created. 3 child URL paths were also updated.');
    });

    it('uses singular redirect wording when only one redirect was created across multiple direct edits', () => {
        dispatchReport({
            pagesUpdated: 2,
            redirectsCreated: 1,
            entries: [entry(10, 'Visible'), entry(11, 'Hidden')],
        });

        const infoCalls = notificationCalls.filter((call) => call.type === 'info');
        expect(infoCalls).to.have.lengthOf(1);
        expect(infoCalls[0].title).to.equal('2 URL paths updated, redirect created');
        expect(infoCalls[0].message).to.equal('URL paths updated for: "Visible" (UID 10), "Hidden" (UID 11). 1 redirect was created.');
    });

    it('renders entry list for multiple direct edits (multi-edit form)', () => {
        dispatchReport({
            pagesUpdated: 3,
            redirectsCreated: 0,
            entries: [entry(1, 'Page A'), entry(2, 'Page B'), entry(3, 'Page C')],
        });

        const infoCalls = notificationCalls.filter((call) => call.type === 'info');
        expect(infoCalls).to.have.lengthOf(1);
        expect(infoCalls[0].title).to.equal('3 URL paths updated');
        expect(infoCalls[0].message).to.equal('URL paths updated for: "Page A" (UID 1), "Page B" (UID 2), "Page C" (UID 3).');
    });

    it('renders cascadeRoot template for the recursive context-menu flow', () => {
        dispatchReport({
            pagesUpdated: 3,
            redirectsCreated: 0,
            entries: [],
            cascadeRoot: {
                pageId: 49,
                title: 'Recursive Parent',
                correlations: {
                    correlationIdSlugUpdate: 'recursive-49/slug',
                    correlationIdRedirectCreation: 'recursive-49/redirect',
                },
            },
        });

        const infoCalls = notificationCalls.filter((call) => call.type === 'info');
        expect(infoCalls).to.have.lengthOf(1);
        expect(infoCalls[0].title).to.equal('URL paths regenerated');
        expect(infoCalls[0].message).to.equal('3 URL paths under "Recursive Parent" (UID 49) were regenerated.');
        const revertRedirect = (infoCalls[0].actions ?? []).find((action) => action.label === 'Revert redirect');
        expect(revertRedirect, 'Recursive flow never creates redirects, so the Revert redirect button must be absent').to.be.undefined;
        const revertUpdate = (infoCalls[0].actions ?? []).find((action) => action.label === 'Revert update');
        expect(revertUpdate, 'Revert update must be present in cascadeRoot flow').to.not.be.undefined;
    });

    it('suppresses core notification: typo3:redirects:slugChanged does not trigger Notification.info', () => {
        document.dispatchEvent(
            new CustomEvent('typo3:redirects:slugChanged', {
                detail: {
                    correlations: { correlationIdSlugUpdate: 'x/y', correlationIdRedirectCreation: 'x/z' },
                    autoUpdateSlugs: true,
                    autoCreateRedirects: true,
                },
            }),
        );

        expect(notificationCalls.filter((call) => call.type === 'info')).to.have.lengthOf(0);
    });

    it('dedups duplicate report dispatches within the 200ms window', () => {
        const detail = {
            pagesUpdated: 1,
            redirectsCreated: 0,
            entries: [entry(42, 'Conflict Test Page')],
        };
        dispatchReport(detail);
        dispatchReport(detail);

        const infoCalls = notificationCalls.filter((call) => call.type === 'info');
        expect(infoCalls).to.have.lengthOf(1);
    });
});
