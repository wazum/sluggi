import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
import Modal from '@typo3/backend/modal.js';
import { SeverityEnum } from '@typo3/backend/enum/severity.js';

interface UpdateResponse {
    success: boolean;
    updated: number;
    skipped: number;
    message?: string;
    correlations?: {
        correlationIdSlugUpdate: string;
        correlationIdRedirectCreation: string;
    };
}

function recursiveSlugUpdate(_table: string, uid: string): void {
    Modal.confirm(
        TYPO3.lang['contextMenu.recursiveSlugUpdate.confirm.title'] || 'Regenerate URL Paths',
        TYPO3.lang['contextMenu.recursiveSlugUpdate.confirm.message'] || 'This will regenerate URL paths for all child pages based on their source fields. Continue?',
        SeverityEnum.warning,
        [
            {
                text: TYPO3.lang['contextMenu.recursiveSlugUpdate.button.cancel'] || 'Cancel',
                active: true,
                btnClass: 'btn-default',
                trigger: (): void => {
                    Modal.dismiss();
                },
            },
            {
                text: TYPO3.lang['contextMenu.recursiveSlugUpdate.confirm.title'] || 'Regenerate URL Paths',
                btnClass: 'btn-warning',
                trigger: (): void => {
                    Modal.dismiss();
                    void performUpdate(uid);
                },
            },
        ],
    );
}

async function performUpdate(uid: string): Promise<void> {
    try {
        const response = await new AjaxRequest(TYPO3.settings.ajaxUrls.sluggi_recursive_slug_update)
            .withQueryArguments({ id: uid })
            .get();

        const result = await response.resolve() as UpdateResponse;

        if (result.success) {
            const title = TYPO3.lang['contextMenu.recursiveSlugUpdate.confirm.title'] || 'URL Paths';

            if (result.updated > 0) {
                const parts: string[] = [];
                parts.push(`${result.updated} updated`);
                if (result.skipped > 0) {
                    parts.push(`${result.skipped} skipped`);
                }
                Notification.success(title, parts.join(', ') + '.');

                if (result.correlations) {
                    document.dispatchEvent(new CustomEvent('typo3:redirects:slugChanged', {
                        detail: {
                            componentName: 'redirects',
                            eventName: 'slugChanged',
                            correlations: result.correlations,
                            autoUpdateSlugs: true,
                            autoCreateRedirects: false,
                        },
                    }));
                }
            } else {
                Notification.info(title, 'No URL paths needed updating.');
            }

            document.dispatchEvent(new CustomEvent('typo3:pagetree:refresh'));
        } else {
            Notification.error('Error', result.message || 'Update failed.');
        }
    } catch {
        Notification.error('Error', 'An unexpected error occurred.');
    }
}

export default { recursiveSlugUpdate };
