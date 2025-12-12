export interface SlugProposalResponse {
    hasConflicts: boolean;
    manual: string;
    proposal: string;
    inaccessibleSegments?: string;
    lastSegmentOnly?: boolean;
}

export type ProposalMode = 'auto' | 'recreate' | 'manual';

export type ComponentMode = 'view' | 'edit';

declare global {
    interface Window {
        TYPO3: {
            settings: {
                ajaxUrls: Record<string, string>;
            };
            lang: Record<string, string>;
        };
    }
}
