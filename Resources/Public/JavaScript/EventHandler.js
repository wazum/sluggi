// Add a timeout for the info alert
define(['require', 'exports', 'TYPO3/CMS/Core/Ajax/AjaxRequest', 'TYPO3/CMS/Backend/Notification', 'TYPO3/CMS/Backend/ActionButton/DeferredAction'], (function (e, t, r, n, i) {
    'use strict';
    return new class {
        constructor () {
            document.addEventListener('typo3:redirects:slugChanged', e => this.onSlugChanged(e.detail));
        }

        dispatchCustomEvent (e, t = null) {
            const r = new CustomEvent(e, { detail: t });
            document.dispatchEvent(r);
        }

        onSlugChanged (e) {
            let t = [];
            const r = e.correlations;
            e.autoUpdateSlugs && t.push({
                label: TYPO3.lang['notification.redirects.button.revert_update'],
                action: new i(() => this.revert([r.correlationIdSlugUpdate, r.correlationIdRedirectCreation]))
            }), e.autoCreateRedirects && t.push({
                label: TYPO3.lang['notification.redirects.button.revert_redirect'],
                action: new i(() => this.revert([r.correlationIdRedirectCreation]))
            });
            let o = TYPO3.lang['notification.slug_only.title'], a = TYPO3.lang['notification.slug_only.message'];
            e.autoCreateRedirects && (o = TYPO3.lang['notification.slug_and_redirects.title'], a = TYPO3.lang['notification.slug_and_redirects.message']), n.info(o, a, 20, t);
        }

        revert (e) {
            const t = new r(TYPO3.settings.ajaxUrls.redirects_revert_correlation).withQueryArguments({ correlation_ids: e }).get();
            return t.then(async e => {
                const t = await e.resolve();
                'ok' === t.status && n.success(t.title, t.message), 'error' === t.status && n.error(t.title, t.message);
            }).catch(() => {
                n.error(TYPO3.lang.redirects_error_title, TYPO3.lang.redirects_error_message);
            }), t;
        }
    };
}));
