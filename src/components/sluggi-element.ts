import { LitElement, html, unsafeCSS, nothing } from 'lit';
import { customElement, property, state, query } from 'lit/decorators.js';
import Modal from '@typo3/backend/modal.js';
import Severity from '@typo3/backend/severity.js';
import type { ComponentMode } from '@/types';
import { lockIcon, editIcon, refreshIcon, checkIcon, closeIcon, syncOnIcon, syncOffIcon } from './icons.js';
import styles from '../styles/sluggi-element.scss?inline';

@customElement('sluggi-element')
export class SluggiElement extends LitElement {
    static override styles = unsafeCSS(styles);

    @property({ type: String })
    value = '';

    @property({ type: String })
    prefix = '';

    @property({ type: Boolean, reflect: true })
    loading = false;

    @property({ type: Boolean, attribute: 'show-regenerate' })
    showRegenerate = true;

    @property({ type: String, attribute: 'fallback-character' })
    fallbackCharacter = '-';

    @property({ type: Object, attribute: 'labels', converter: {
        fromAttribute: (value: string | null) => {
            if (!value) return {};
            try {
                return JSON.parse(value);
            } catch {
                return {};
            }
        }
    }})
    labels: Record<string, string> = {};

    @property({ type: Boolean, attribute: 'is-locked' })
    isLocked = false;

    @property({ type: Boolean, attribute: 'last-segment-only' })
    lastSegmentOnly = false;

    @property({ type: String, attribute: 'locked-prefix' })
    lockedPrefix = '';

    @property({ type: Boolean, attribute: 'has-post-modifiers' })
    hasPostModifiers = false;

    @property({ type: String, attribute: 'required-source-fields' })
    requiredSourceFields = '';

    @property({ type: Boolean, attribute: 'is-synced' })
    isSynced = false;

    @property({ type: Boolean, attribute: 'sync-feature-enabled' })
    syncFeatureEnabled = false;

    // =========================================================================
    // Properties: Conflict State
    // =========================================================================

    @property({ type: Boolean, attribute: 'has-conflict' })
    hasConflict = false;

    @property({ type: String, attribute: 'conflict-proposal' })
    conflictProposal = '';

    // =========================================================================
    // Properties: AJAX Parameters
    // =========================================================================

    @property({ type: String, attribute: 'page-id' })
    pageId = '';

    @property({ type: String, attribute: 'record-id' })
    recordId = '';

    @property({ type: String, attribute: 'table-name' })
    tableName = '';

    @property({ type: String, attribute: 'field-name' })
    fieldName = '';

    @property({ type: String })
    language = '';

    @property({ type: String })
    signature = '';

    @property({ type: String })
    command = '';

    @property({ type: String, attribute: 'parent-page-id' })
    parentPageId = '';

    @property({ type: Boolean, attribute: 'include-uid' })
    includeUid = false;

    // =========================================================================
    // Internal State
    // =========================================================================

    @state()
    private mode: ComponentMode = 'view';

    @state()
    private editValue = '';

    @state()
    private conflictingSlug = '';

    @state()
    private valueBeforeEdit = '';

    @query('input.sluggi-input')
    private inputElement?: HTMLInputElement;

    private sourceFieldElements: Map<string, HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement> = new Map();
    private boundSourceFieldHandler: (event: Event) => void;
    private boundSourceFieldInitHandler: () => void;

    // =========================================================================
    // Constructor & Lifecycle
    // =========================================================================

    constructor() {
        super();
        this.boundSourceFieldHandler = this.handleSourceFieldChange.bind(this);
        this.boundSourceFieldInitHandler = this.handleSourceFieldInit.bind(this);
    }

    override connectedCallback() {
        super.connectedCallback();
        this.setupSourceFieldListeners();
    }

    override disconnectedCallback() {
        super.disconnectedCallback();
        this.removeSourceFieldListeners();
    }

    // =========================================================================
    // Computed Properties
    // =========================================================================

    private get canRegenerate(): boolean {
        if (!this.showRegenerate || this.isSynced) return false;
        if (this.hasPostModifiers) return true;
        return this.sourceFieldElements.size > 0 && this.hasNonEmptySourceFieldValue();
    }

    private get showSyncToggle(): boolean {
        return this.syncFeatureEnabled && this.sourceFieldElements.size > 0 && !this.isLocked;
    }

    private get computedPrefix(): string {
        if (this.lastSegmentOnly && this.value) {
            const parts = this.value.split('/').filter(Boolean);
            if (parts.length > 1) {
                parts.pop();
                return '/' + parts.join('/');
            }
        }
        if (this.lockedPrefix) {
            return this.lockedPrefix;
        }
        return this.prefix;
    }

    private get editableValue(): string {
        if (this.lastSegmentOnly && this.value) {
            const parts = this.value.split('/').filter(Boolean);
            if (parts.length > 0) {
                return '/' + parts[parts.length - 1];
            }
        }
        if (this.lockedPrefix && this.value.startsWith(this.lockedPrefix)) {
            return this.value.slice(this.lockedPrefix.length) || '/';
        }
        return this.value;
    }

    private get showPlaceholder(): boolean {
        if (this.sourceFieldElements.size === 0) {
            return false;
        }
        return !this.hasNonEmptySourceFieldValue();
    }

    private get hiddenInputValue(): string {
        if (this.lastSegmentOnly || this.lockedPrefix) {
            return this.computedPrefix + this.editableValue;
        }
        return this.value;
    }

    // =========================================================================
    // Render Methods
    // =========================================================================

    override render() {
        return html`
            <div class="sluggi-wrapper ${this.isLocked ? 'locked' : ''}">
                ${this.computedPrefix ? html`<span class="sluggi-prefix">${this.computedPrefix}</span>` : nothing}

                ${this.mode === 'view' ? this.renderViewMode() : this.renderEditMode()}

                <div class="sluggi-controls">
                    ${this.renderControls()}
                </div>
            </div>
        `;
    }

    private renderViewMode() {
        const isEditable = !this.isLocked && !this.isSynced;
        const editable = this.editableValue;
        const classes = [
            'sluggi-editable',
            this.isLocked ? 'locked' : '',
            this.isSynced ? 'synced' : '',
        ].filter(Boolean).join(' ');

        return html`
            <span
                class="${classes}"
                role="${isEditable ? 'button' : nothing}"
                tabindex="${isEditable ? '0' : '-1'}"
                aria-label="${isEditable ? `Click to edit slug: ${editable}` : editable}"
                @click="${this.handleEditableClick}"
                @keydown="${this.handleEditableKeydown}"
            >${editable || '/'}${this.showPlaceholder ? html`<span class="sluggi-placeholder">/new-page</span>` : nothing}</span>
        `;
    }

    private renderEditMode() {
        return html`
            <span class="sluggi-input-prefix">/</span>
            <input
                type="text"
                class="sluggi-input"
                aria-label="Edit URL slug"
                .value="${this.editValue}"
                @input="${this.handleInput}"
                @keydown="${this.handleInputKeydown}"
                @blur="${this.handleBlur}"
            />
        `;
    }

    private renderControls() {
        if (this.loading) {
            return html`
                <span class="sluggi-spinner" aria-label="Loading..."></span>
                ${this.renderSyncToggle()}
            `;
        }

        if (this.isLocked) {
            return html`
                <span class="sluggi-lock-icon" aria-label="This slug is locked">
                    ${lockIcon}
                </span>
            `;
        }

        if (this.mode === 'view') {
            return html`
                ${!this.isSynced ? html`
                    <button
                        type="button"
                        class="btn btn-sm btn-default sluggi-edit-btn"
                        aria-label="Edit slug"
                        @click="${this.enterEditMode}"
                    >
                        ${editIcon}
                    </button>
                ` : nothing}
                ${this.canRegenerate ? html`
                    <button
                        type="button"
                        class="btn btn-sm btn-default sluggi-regenerate-btn"
                        aria-label="Regenerate slug from title"
                        @click="${this.handleRegenerate}"
                    >
                        ${refreshIcon}
                    </button>
                ` : nothing}
                ${this.renderSyncToggle()}
            `;
        }

        return html`
            <button
                type="button"
                class="btn btn-sm btn-default sluggi-cancel-btn"
                aria-label="Cancel editing"
                @mousedown="${this.handleCancelButtonMousedown}"
            >
                ${closeIcon}
            </button>
            <button
                type="button"
                class="btn btn-sm btn-default sluggi-save-btn"
                aria-label="Save slug"
                @mousedown="${this.handleSaveButtonMousedown}"
            >
                ${checkIcon}
            </button>
        `;
    }

    private renderSyncToggle() {
        if (!this.showSyncToggle) return nothing;

        return html`
            <div class="sluggi-sync-wrapper">
                <button
                    type="button"
                    class="sluggi-sync-toggle ${this.isSynced ? 'is-synced' : ''}"
                    aria-label="${this.isSynced ? 'Disable automatic sync' : 'Enable automatic sync'}"
                    title="${this.isSynced ? 'Auto-sync enabled: slug updates with title' : 'Auto-sync disabled: click to enable'}"
                    @click="${this.toggleSync}"
                >
                    <span class="sluggi-sync-label">sync</span>
                    <span class="sluggi-sync-icons">
                        <span class="sluggi-sync-icon sluggi-sync-icon-on">${syncOnIcon}</span>
                        <span class="sluggi-sync-icon sluggi-sync-icon-off">${syncOffIcon}</span>
                    </span>
                </button>
            </div>
        `;
    }

    // =========================================================================
    // Public API
    // =========================================================================

    enterEditMode() {
        if (this.isLocked) return;

        this.mode = 'edit';
        this.valueBeforeEdit = this.value;
        this.clearConflictState();
        this.editValue = this.editableValue.replace(/^\//, '');
        this.dispatchEvent(new CustomEvent('sluggi-edit-start', { bubbles: true, composed: true }));

        this.updateComplete.then(() => {
            this.inputElement?.focus();
            this.inputElement?.select();
        });
    }

    saveSlug() {
        if (this.mode !== 'edit') return;

        const sanitizedValue = this.sanitizeSlug(this.editValue, true);

        // In lastSegmentOnly mode, revert to original if segment is empty
        if (this.lastSegmentOnly && !sanitizedValue) {
            this.cancelEdit();
            return;
        }

        const oldValue = this.value;
        const fullNewValue = this.buildFullSlug('/' + sanitizedValue);

        this.conflictingSlug = fullNewValue;
        this.value = fullNewValue;
        this.mode = 'view';

        if (oldValue !== this.value) {
            this.dispatchEvent(new CustomEvent('sluggi-change', {
                bubbles: true,
                composed: true,
                detail: { value: this.value, oldValue },
            }));
            this.notifyFormEngineOfChange();
        }

        this.sendSlugProposal('manual');
    }

    setProposal(proposal: string, hasConflict = false, conflictingSlug = '') {
        if (hasConflict && this.value !== proposal) {
            this.hasConflict = true;
            this.conflictProposal = proposal;
            this.conflictingSlug = conflictingSlug || this.conflictingSlug || this.value;
            this.showConflictModal();
        } else {
            this.value = proposal;
            this.clearConflictState();
            this.dispatchEvent(new CustomEvent('sluggi-change', {
                bubbles: true,
                composed: true,
                detail: { value: this.value }
            }));
            this.notifyFormEngineOfChange();
        }
    }

    async sendSlugProposal(mode: 'auto' | 'recreate' | 'manual') {
        this.dispatchEvent(new CustomEvent('sluggi-request-proposal', {
            bubbles: true,
            composed: true,
            detail: {
                mode,
                pageId: this.pageId,
                recordId: this.recordId,
                tableName: this.tableName,
                fieldName: this.fieldName,
                language: this.language,
                signature: this.signature,
                command: this.command,
                parentPageId: this.parentPageId,
                currentValue: this.value,
            }
        }));

        const ajaxUrl = this.getAjaxUrl();
        if (!ajaxUrl) {
            return;
        }

        this.loading = true;

        try {
            const formData = new FormData();
            formData.append('mode', mode);
            formData.append('tableName', this.tableName);
            formData.append('pageId', this.pageId);
            formData.append('parentPageId', this.parentPageId);
            formData.append('recordId', this.recordId);
            formData.append('language', this.language);
            formData.append('fieldName', this.fieldName);
            formData.append('command', this.command);
            formData.append('signature', this.signature);

            if (mode === 'manual') {
                formData.append('values[manual]', this.value);
            } else {
                const fieldValues = this.collectFormFieldValues();
                for (const [key, value] of Object.entries(fieldValues)) {
                    formData.append(`values[${key}]`, value);
                }
            }

            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`HTTP error ${response.status}`);
            }

            const data = await response.json();
            this.setProposal(data.proposal, data.hasConflicts, data.slug);
        } catch (error) {
            console.error('Slug proposal request failed:', error);
        } finally {
            this.loading = false;
        }
    }

    // =========================================================================
    // Event Handlers
    // =========================================================================

    private handleEditableClick() {
        if (!this.isLocked && !this.isSynced) {
            this.enterEditMode();
        }
    }

    private handleEditableKeydown(e: KeyboardEvent) {
        if ((e.key === 'Enter' || e.key === ' ') && !this.isLocked && !this.isSynced) {
            e.preventDefault();
            this.enterEditMode();
        }
    }

    private handleInput(e: InputEvent) {
        const input = e.target as HTMLInputElement;
        let value = this.sanitizeSlug(input.value, false);

        if (this.lastSegmentOnly) {
            value = value.replace(/^\/+/, '');
            value = value.replace(/\//g, this.fallbackCharacter);
            value = this.sanitizeSlug(value, false);
        }

        input.value = value;
        this.editValue = value;
    }

    private handleInputKeydown(e: KeyboardEvent) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.saveSlug();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            this.cancelEdit();
        }
    }

    private handleBlur() {
        if (this.mode === 'edit') {
            this.saveSlug();
        }
    }

    private handleSaveButtonMousedown(e: MouseEvent) {
        e.preventDefault();
        this.saveSlug();
    }

    private handleCancelButtonMousedown(e: MouseEvent) {
        e.preventDefault();
        this.cancelEdit();
    }

    private handleRegenerate() {
        this.sendSlugProposal('recreate');
    }

    private handleSourceFieldChange(event: Event) {
        const changedElement = event.target as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;
        if (changedElement.value.trim() === '') {
            this.requestUpdate();
            return;
        }

        if (!this.hasRequiredFieldValues()) {
            return;
        }

        const shouldAutoSync = this.syncFeatureEnabled
            ? this.isSynced
            : this.command === 'new';
        if (shouldAutoSync && this.mode === 'view') {
            this.sendSlugProposal('recreate');
        }
    }

    private hasRequiredFieldValues(): boolean {
        if (!this.requiredSourceFields) {
            return true;
        }

        const requiredFields = this.requiredSourceFields.split(',').map(f => f.trim()).filter(Boolean);
        for (const fieldName of requiredFields) {
            const element = this.sourceFieldElements.get(fieldName);
            if (element && element.value.trim() === '') {
                return false;
            }
        }
        return true;
    }

    // =========================================================================
    // Private Helpers: Edit Operations
    // =========================================================================

    private cancelEdit() {
        this.mode = 'view';
        this.editValue = '';
        this.dispatchEvent(new CustomEvent('sluggi-edit-cancel', { bubbles: true, composed: true }));
    }

    private buildFullSlug(segment: string): string {
        if (this.lastSegmentOnly || this.lockedPrefix) {
            return this.computedPrefix + segment;
        }
        return segment;
    }

    private clearConflictState() {
        this.hasConflict = false;
        this.conflictProposal = '';
        this.conflictingSlug = '';
    }

    // =========================================================================
    // Private Helpers: Conflict Resolution
    // =========================================================================

    private useSuggestion() {
        this.value = this.conflictProposal;
        this.clearConflictState();
        this.dispatchEvent(new CustomEvent('sluggi-change', {
            bubbles: true,
            composed: true,
            detail: { value: this.value }
        }));
        this.notifyFormEngineOfChange();
    }

    private revertToOriginal() {
        if (this.valueBeforeEdit) {
            this.value = this.valueBeforeEdit;
            this.notifyFormEngineOfChange();
        }
        this.clearConflictState();
    }

    private showConflictModal() {
        const title = this.getLabel('conflict.title');
        const message = this.getLabel('conflict.message', this.conflictingSlug);
        const suggestion = this.getLabel('conflict.suggestion', this.conflictProposal);
        const cancelButton = this.getLabel('conflict.button.cancel');
        const useSuggestionButton = this.getLabel('conflict.button.useSuggestion');

        Modal.confirm(
            title,
            `${message}\n\n${suggestion}`,
            Severity.warning,
            [
                {
                    text: cancelButton,
                    active: true,
                    trigger: () => {
                        Modal.dismiss();
                        this.revertToOriginal();
                    },
                },
                {
                    text: useSuggestionButton,
                    btnClass: 'btn-warning',
                    trigger: () => {
                        Modal.dismiss();
                        this.useSuggestion();
                    },
                },
            ]
        );
    }

    // =========================================================================
    // Private Helpers: Sync Feature
    // =========================================================================

    private toggleSync() {
        this.isSynced = !this.isSynced;
        this.notifySyncFieldOfChange();
        this.updateSourceBadgeVisibility();

        if (this.isSynced && this.hasNonEmptySourceFieldValue()) {
            this.sendSlugProposal('recreate');
        }
    }

    private updateSourceBadgeVisibility() {
        const badges = document.querySelectorAll<HTMLElement>('.sluggi-source-badge');
        for (const badge of badges) {
            if (this.isSynced) {
                badge.style.removeProperty('display');
            } else {
                badge.style.display = 'none';
            }
            badge.parentElement?.classList.toggle('input-group', this.isSynced);
        }
    }

    private hasNonEmptySourceFieldValue(): boolean {
        for (const element of this.sourceFieldElements.values()) {
            if (element.value.trim() !== '') {
                return true;
            }
        }
        return false;
    }

    private notifySyncFieldOfChange() {
        const syncInput = this.parentElement?.querySelector('.sluggi-sync-field') as HTMLInputElement | null;
        if (syncInput) {
            syncInput.value = this.isSynced ? '1' : '0';
            syncInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // =========================================================================
    // Private Helpers: Source Field Listeners
    // =========================================================================

    private setupSourceFieldListeners() {
        const sourceElements = document.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('[data-sluggi-source]');

        for (const element of sourceElements) {
            const inputName = element.getAttribute('data-formengine-input-name') || element.getAttribute('name') || '';
            const match = inputName.match(/data\[[^\]]+\]\[[^\]]+\]\[([^\]]+)\]/);
            const fieldName = match ? match[1] : null;
            if (fieldName) {
                this.sourceFieldElements.set(fieldName, element);
                element.addEventListener('change', this.boundSourceFieldHandler);
                element.addEventListener('input', this.boundSourceFieldInitHandler);
                element.addEventListener('formengine:input:initialized', this.boundSourceFieldInitHandler);
            }
        }
    }

    private removeSourceFieldListeners() {
        for (const element of this.sourceFieldElements.values()) {
            element.removeEventListener('change', this.boundSourceFieldHandler);
            element.removeEventListener('input', this.boundSourceFieldInitHandler);
            element.removeEventListener('formengine:input:initialized', this.boundSourceFieldInitHandler);
        }
        this.sourceFieldElements.clear();
    }

    private sourceFieldInputTimeout: number | null = null;

    private handleSourceFieldInit() {
        if (this.sourceFieldInputTimeout) {
            clearTimeout(this.sourceFieldInputTimeout);
        }
        this.sourceFieldInputTimeout = window.setTimeout(() => {
            this.requestUpdate();
            this.sourceFieldInputTimeout = null;
        }, 150);
    }

    private collectFormFieldValues(): Record<string, string> {
        const values: Record<string, string> = {};
        for (const [fieldName, element] of this.sourceFieldElements) {
            values[fieldName] = element.value;
        }
        if (this.includeUid) {
            values.uid = this.recordId;
        }
        return values;
    }

    // =========================================================================
    // Private Helpers: Form Integration
    // =========================================================================

    private notifyFormEngineOfChange() {
        const hiddenInput = this.parentElement?.querySelector('.sluggi-hidden-field') as HTMLInputElement | null;
        if (hiddenInput) {
            hiddenInput.value = this.hiddenInputValue;
            hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // =========================================================================
    // Private Helpers: Sanitization & AJAX
    // =========================================================================

    private sanitizeSlug(value: string, trim = true): string {
        const fallback = this.fallbackCharacter;
        const fallbackEscaped = fallback.replace(/[-.*+?^${}()|[\]\\]/g, '\\$&');

        value = value.toLowerCase();
        value = value.replace(/[ \t\u00A0+_]+/g, fallback);

        if (fallback !== '-') {
            value = value.replace(/-+/g, fallback);
        }

        value = value.replace(new RegExp(`[^\\p{L}\\p{M}0-9\\/${fallbackEscaped}]`, 'gu'), '');

        if (fallback) {
            value = value.replace(new RegExp(`${fallbackEscaped}{2,}`, 'g'), fallback);
        }

        if (trim) {
            value = value.replace(new RegExp(`^${fallbackEscaped}+|${fallbackEscaped}+$`, 'g'), '');
        }

        return value;
    }

    private getLabel(key: string, ...replacements: string[]): string {
        let label = this.labels[key] || key;
        replacements.forEach((replacement) => {
            label = label.replace('%s', replacement);
        });
        return label;
    }

    private getAjaxUrl(): string | null {
        const typo3 = (window as unknown as { TYPO3?: { settings?: { ajaxUrls?: Record<string, string> } } }).TYPO3;
        return typo3?.settings?.ajaxUrls?.record_slug_suggest ?? null;
    }
}

declare global {
    interface HTMLElementTagNameMap {
        'sluggi-element': SluggiElement;
    }
}
