window.TYPO3 = {
    settings: {
        ajaxUrls: {
            record_slug_suggest: '/api/slug-suggest',
        },
    },
    lang: {},
};

function slugify(text: string): string {
    return text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s]+/g, '-')
        .replace(/-{2,}/g, '-')
        .replace(/^-+|-+$/g, '');
}

function delay(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

const originalFetch = window.fetch.bind(window);

window.fetch = async (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url;

    if (url === '/api/slug-suggest' && init?.method === 'POST') {
        const formData = init.body as FormData;
        const mode = formData.get('mode') as string;

        let proposal: string;
        let hasConflicts = false;

        if (mode === 'manual') {
            proposal = formData.get('values[manual]') as string;
            hasConflicts = Math.random() < 0.3;
            if (hasConflicts) {
                proposal = proposal + '-1';
            }
        } else {
            const title = (formData.get('values[title]') as string) ?? '';
            const navTitle = (formData.get('values[nav_title]') as string) ?? '';
            const source = navTitle || title;
            proposal = source ? '/' + slugify(source) : '/';
        }

        await delay(300 + Math.random() * 400);

        return new Response(
            JSON.stringify({
                hasConflicts,
                proposal,
                manual: proposal,
            }),
            {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            },
        );
    }

    return originalFetch(input, init);
};

import '../src/index.js';

const labels: Record<string, string> = {
    'conflict.title': 'URL path conflict',
    'conflict.message': 'The URL path "%s" is already in use by another page.',
    'conflict.suggestion': 'Suggested alternative: %s',
    'conflict.button.cancel': 'Revert to original',
    'conflict.button.useSuggestion': 'Use suggestion',
    'toggle.sync.on': 'Auto-sync enabled: URL path updates with title',
    'toggle.sync.off': 'Auto-sync disabled: click to enable',
    'toggle.lock.on': 'URL path is locked: click to unlock',
    'toggle.lock.off': 'URL path is unlocked: click to lock',
};

customElements.whenDefined('sluggi-element').then(async () => {
    const scopedBadgeUpdate = function (this: any) {
        const card = this.closest('.demo-card');
        if (!card) return;
        const badges = card.querySelectorAll<HTMLElement>('.sluggi-source-badge');
        for (const badge of badges) {
            if (this.isSynced) {
                badge.style.removeProperty('display');
            } else {
                badge.style.display = 'none';
            }
            badge.parentElement?.classList.toggle('input-group', this.isSynced);
        }
    };

    const elements = Array.from(document.querySelectorAll('sluggi-element')) as any[];
    for (const el of elements) {
        el.labels = labels;
        el.updateSourceBadgeVisibility = scopedBadgeUpdate.bind(el);
    }

    await Promise.all(elements.map((el) => el.updateComplete));
    for (const el of elements) {
        el.updateSourceBadgeVisibility();
    }
});
