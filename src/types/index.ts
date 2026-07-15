export interface SlugProposalResponse {
    hasConflicts: boolean;
    manual: string;
    proposal: string;
    /** The original slug, added by sluggi's XCLASS when a conflict exists */
    slug?: string;
}

export function isSlugProposalResponse(value: unknown): value is SlugProposalResponse {
    if (typeof value !== 'object' || value === null) {
        return false;
    }
    const candidate = value as Record<string, unknown>;
    return typeof candidate.proposal === 'string'
        && typeof candidate.hasConflicts === 'boolean'
        && (candidate.slug === undefined || typeof candidate.slug === 'string');
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
    disabledTitle?: string;
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
