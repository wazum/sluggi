import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
import Modal from '@typo3/backend/modal.js';
import { SeverityEnum } from '@typo3/backend/enum/severity.js';

interface UpdateResponse {
    success: boolean;
    updated: number;
    skipped: number;
    title?: string;
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
            .post({ id: uid });

        const result = await response.resolve() as UpdateResponse;

        if (result.success) {
            if (result.updated > 0 && result.correlations) {
                document.dispatchEvent(new CustomEvent('typo3:sluggi:slugChangeReport', {
                    detail: {
                        entries: [],
                        pagesUpdated: result.updated,
                        redirectsCreated: 0,
                        cascadeRoot: {
                            pageId: Number(uid),
                            title: result.title ?? '',
                            correlations: result.correlations,
                        },
                    },
                }));
            } else if (result.updated === 0) {
                const title = TYPO3.lang['contextMenu.recursiveSlugUpdate.confirm.title'] || 'URL Paths';
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
