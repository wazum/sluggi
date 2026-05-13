import { expect } from '@open-wc/testing';

function setupTYPO3Global(): void {
    (window as unknown as Record<string, unknown>).TYPO3 = {
        settings: { ajaxUrls: { redirects_revert_correlation: '/api/revert' } },
        lang: {
            'notification.slugReport.singleSlug.title': 'URL path updated',
            'notification.slugReport.singleSlug.message': 'The URL was changed.',
            'notification.slugReport.singleSlugAndRedirect.title': 'URL path updated, redirect created',
            'notification.slugReport.singleSlugAndRedirect.message': 'A redirect was added.',
            'notification.slugReport.multipleSlugs.title': '%d URL paths updated',
            'notification.slugReport.multipleSlugs.message': '%d slugs changed.',
            'notification.slugReport.multipleSlugsAndRedirects.title': '{pages} URL paths updated, {redirects} redirects created',
            'notification.slugReport.multipleSlugsAndRedirects.message': '{pages} slugs changed, {redirects} redirects added.',
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
// The default export of redirect-notification-handler.ts attaches the
// listeners exactly once for the entire suite.
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

function dispatchReport(detail: {
    pagesUpdated: number;
    redirectsCreated: number;
    entries?: Array<{ pageId: number; correlations: { correlationIdSlugUpdate: string; correlationIdRedirectCreation: string } }>;
}): void {
    document.dispatchEvent(
        new CustomEvent('typo3:sluggi:slugChangeReport', {
            detail: { entries: [], ...detail },
        }),
    );
}

describe('RedirectNotificationHandler - slugChangeReport rendering', () => {
    beforeEach(() => {
        resetNotifications();
    });

    it('renders single-slug title when pagesUpdated=1 redirectsCreated=0', () => {
        dispatchReport({ pagesUpdated: 1, redirectsCreated: 0, entries: [] });

        const infoCalls = notificationCalls.filter((call) => call.type === 'info');
        expect(infoCalls).to.have.lengthOf(1);
        const call = infoCalls[0];
        expect(call.title).to.equal('URL path updated');
        expect(call.message).to.equal('The URL was changed.');
        const revertRedirect = (call.actions ?? []).find((action) => action.label === 'Revert redirect');
        expect(revertRedirect, 'Revert redirect button must be absent when no redirects were created').to.be.undefined;
    });

    it('renders single-slug-and-redirect title when pagesUpdated=1 redirectsCreated=1', () => {
        dispatchReport({
            pagesUpdated: 1,
            redirectsCreated: 1,
            entries: [
                {
                    pageId: 42,
                    correlations: {
                        correlationIdSlugUpdate: 'a/x',
                        correlationIdRedirectCreation: 'a/y',
                    },
                },
            ],
        });

        const infoCalls = notificationCalls.filter((call) => call.type === 'info');
        expect(infoCalls).to.have.lengthOf(1);
        const call = infoCalls[0];
        expect(call.title).to.equal('URL path updated, redirect created');
        expect(call.message).to.equal('A redirect was added.');
        const revertRedirect = (call.actions ?? []).find((action) => action.label === 'Revert redirect');
        expect(revertRedirect, 'Revert redirect button must be present when a redirect was created').to.not.be.undefined;
    });

    it('renders multiple-slugs title with %d substituted when pagesUpdated>1 redirectsCreated=0', () => {
        dispatchReport({ pagesUpdated: 7, redirectsCreated: 0, entries: [] });

        const infoCalls = notificationCalls.filter((call) => call.type === 'info');
        expect(infoCalls).to.have.lengthOf(1);
        expect(infoCalls[0].title).to.equal('7 URL paths updated');
        expect(infoCalls[0].message).to.equal('7 slugs changed.');
    });

    it('renders multiple-slugs-and-redirects title with both %d substituted', () => {
        dispatchReport({ pagesUpdated: 7, redirectsCreated: 6, entries: [] });

        const infoCalls = notificationCalls.filter((call) => call.type === 'info');
        expect(infoCalls).to.have.lengthOf(1);
        expect(infoCalls[0].title).to.equal('7 URL paths updated, 6 redirects created');
        expect(infoCalls[0].message).to.equal('7 slugs changed, 6 redirects added.');
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
});
