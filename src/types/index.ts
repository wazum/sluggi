export interface SlugProposalResponse {
    hasConflicts: boolean;
    manual: string;
    proposal: string;
    inaccessibleSegments?: string;
    lastSegmentOnly?: boolean;
}

export type ProposalMode = 'auto' | 'recreate' | 'manual';

export type ComponentMode = 'view' | 'edit';

export interface ToggleConfig {
    name: string;
    isActive: boolean;
    isDisabled: boolean;
    activeClass: string;
    iconBaseClass: string;
    labelOn: string;
    labelOff: string;
    defaultLabelOn: string;
    defaultLabelOff: string;
    iconOn: unknown;
    iconOff: unknown;
    onToggle: () => void;
}

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
