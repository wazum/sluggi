import { LitElement, html, unsafeCSS, nothing } from 'lit';
import { customElement, property, state, query } from 'lit/decorators.js';
import Modal from '@typo3/backend/modal.js';
import Severity from '@typo3/backend/severity.js';
import type { ComponentMode } from '@/types';
import { lockIcon, editIcon, refreshIcon, checkIcon, closeIcon, syncIcon, syncOnIcon, syncOffIcon, lockOnIcon, lockOffIcon, pathOnIcon, pathOffIcon } from './icons.js';
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

    @property({ type: Boolean, attribute: 'lock-feature-enabled' })
    lockFeatureEnabled = false;

    @property({ type: Boolean, attribute: 'full-path-feature-enabled' })
    fullPathFeatureEnabled = false;

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

    @state()
    private slugGenerated = false;

    @state()
    private syncAnimating = false;

    @state()
    private isFullPathMode = false;

    @state()
    private hasSourceFields = false;

    @query('input.sluggi-input')
    private inputElement?: HTMLInputElement;

    private sourceFieldElements: Map<string, HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement> = new Map();
    private boundSourceFieldHandler: (event: Event) => void;
    private boundSourceFieldInitHandler: () => void;
    private sourceFieldObserver: MutationObserver | null = null;

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
        this.observeSourceFieldInitialization();
    }

    override disconnectedCallback() {
        super.disconnectedCallback();
        this.removeSourceFieldListeners();
        this.sourceFieldObserver?.disconnect();
        this.sourceFieldObserver = null;
    }

    // =========================================================================
    // Computed Properties
    // =========================================================================

    private get canRegenerate(): boolean {
        if (!this.showRegenerate || this.isSynced) return false;
        if (this.hasPostModifiers) return true;
        return this.hasSourceFields && this.hasNonEmptySourceFieldValue();
    }

    private get showSyncToggle(): boolean {
        return this.syncFeatureEnabled && this.hasSourceFields && !this.isLocked;
    }

    private get showLockToggle(): boolean {
        return this.lockFeatureEnabled && !this.isSynced;
    }

    private get showFullPathToggle(): boolean {
        if (!this.fullPathFeatureEnabled) return false;
        if (this.isLocked || this.isSynced) return false;
        return this.lastSegmentOnly || !!this.lockedPrefix;
    }

    private get computedPrefix(): string {
        if (this.isFullPathMode) {
            return this.prefix;
        }
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
        if (this.isFullPathMode) {
            if (this.prefix && this.value.startsWith(this.prefix)) {
                return this.value.slice(this.prefix.length) || '/';
            }
            return this.value;
        }
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
        if (this.command !== 'new') {
            return false;
        }
        if (this.slugGenerated) {
            return false;
        }
        if (!this.hasSourceFields) {
            return false;
        }
        return true;
    }

    private get hiddenInputValue(): string {
        if (this.isFullPathMode) {
            return this.prefix + this.editableValue;
        }
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
            ${this.renderRestrictionNote()}
        `;
    }

    private renderRestrictionNote() {
        if (!this.isSynced && !this.isLocked) return nothing;

        const message = this.isSynced
            ? this.labels.syncRestrictionNote || 'The URL path is automatically synchronized with the source fields.'
            : this.labels.lockRestrictionNote || 'The URL path is locked and cannot be edited.';

        return html`<p class="sluggi-restriction-note">${message}</p>`;
    }

    private renderViewMode() {
        const isEditable = !this.isLocked && !this.isSynced;
        const editable = this.editableValue;
        const cannotEdit = this.isSynced && !this.syncFeatureEnabled;
        const classes = [
            'sluggi-editable',
            this.isLocked ? 'locked' : '',
            this.isSynced ? 'synced' : '',
            cannotEdit ? 'no-edit' : '',
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
                ${this.renderStaticSyncIcon()}
                ${this.renderLockToggle()}
            `;
        }

        if (this.isLocked) {
            return html`
                ${this.showLockToggle ? this.renderLockToggle() : html`
                    <span class="sluggi-lock-icon" aria-label="This slug is locked">
                        ${lockIcon}
                    </span>
                `}
            `;
        }

        if (this.mode === 'view') {
            return html`
                ${!this.isSynced ? html`
                    <button
                        type="button"
                        class="btn btn-sm btn-default sluggi-edit-btn"
                        title="${this.labels['button.edit'] || 'Click to edit the URL path'}"
                        @click="${this.enterEditMode}"
                    >
                        ${editIcon}
                    </button>
                ` : nothing}
                ${this.canRegenerate ? html`
                    <button
                        type="button"
                        class="btn btn-sm btn-default sluggi-regenerate-btn"
                        title="${this.labels['button.regenerate'] || 'Regenerate URL path from source fields'}"
                        @click="${this.handleRegenerate}"
                    >
                        ${refreshIcon}
                    </button>
                ` : nothing}
                ${this.renderFullPathToggle()}
                ${this.renderSyncToggle()}
                ${this.renderStaticSyncIcon()}
                ${this.renderLockToggle()}
            `;
        }

        return html`
            <button
                type="button"
                class="btn btn-sm btn-default sluggi-cancel-btn"
                title="${this.labels['button.cancel'] || 'Cancel editing'}"
                @mousedown="${this.handleCancelButtonMousedown}"
            >
                ${closeIcon}
            </button>
            <button
                type="button"
                class="btn btn-sm btn-default sluggi-save-btn"
                title="${this.labels['button.save'] || 'Save URL path'}"
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
                    title="${this.isSynced ? (this.labels['toggle.sync.on'] || 'Auto-sync enabled: URL path updates with title') : (this.labels['toggle.sync.off'] || 'Auto-sync disabled: click to enable')}"
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

    private renderStaticSyncIcon() {
        if (this.syncFeatureEnabled || !this.isSynced) return nothing;

        return html`
            <span
                class="sluggi-sync-icon-static ${this.syncAnimating ? 'syncing' : ''}"
                title="${this.labels['toggle.sync.static'] || 'Auto-sync enabled: URL path updates automatically'}"
                @animationend="${this.handleSyncAnimationEnd}"
            >
                ${syncIcon}
            </span>
        `;
    }

    private handleSyncAnimationEnd() {
        this.syncAnimating = false;
    }

    private renderLockToggle() {
        if (!this.showLockToggle) return nothing;

        return html`
            <div class="sluggi-lock-wrapper">
                <button
                    type="button"
                    class="sluggi-lock-toggle ${this.isLocked ? 'is-locked' : ''}"
                    title="${this.isLocked ? (this.labels['toggle.lock.on'] || 'URL path is locked: click to unlock') : (this.labels['toggle.lock.off'] || 'URL path is unlocked: click to lock')}"
                    @click="${this.toggleLock}"
                >
                    <span class="sluggi-lock-label">lock</span>
                    <span class="sluggi-lock-icons">
                        <span class="sluggi-lock-icon-toggle sluggi-lock-icon-on">${lockOnIcon}</span>
                        <span class="sluggi-lock-icon-toggle sluggi-lock-icon-off">${lockOffIcon}</span>
                    </span>
                </button>
            </div>
        `;
    }

    private renderFullPathToggle() {
        if (!this.showFullPathToggle) return nothing;

        return html`
            <div class="sluggi-full-path-wrapper">
                <button
                    type="button"
                    class="sluggi-full-path-toggle ${this.isFullPathMode ? 'is-active' : ''}"
                    title="${this.isFullPathMode ? (this.labels['toggle.path.on'] || 'Full path editing enabled: click to restrict') : (this.labels['toggle.path.off'] || 'Click to edit full path')}"
                    @click="${this.toggleFullPath}"
                >
                    <span class="sluggi-full-path-label">path</span>
                    <span class="sluggi-full-path-icons">
                        <span class="sluggi-full-path-icon sluggi-full-path-icon-on">${pathOnIcon}</span>
                        <span class="sluggi-full-path-icon sluggi-full-path-icon-off">${pathOffIcon}</span>
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
        if ((this.lastSegmentOnly || this.lockedPrefix) && !this.isFullPathMode) {
            const proposalPrefix = this.getParentPath(proposal);
            const currentValuePrefix = this.getParentPath(this.value);
            // Use lockedPrefix only if value already follows hierarchy, otherwise use derived prefix
            const expectedPrefix = (this.lockedPrefix && this.value.startsWith(this.lockedPrefix))
                ? this.lockedPrefix
                : currentValuePrefix;

            if (proposalPrefix !== expectedPrefix) {
                this.isFullPathMode = true;
                this.notifyFullPathFieldOfChange();
            } else {
                const proposalParts = proposal.split('/').filter(Boolean);
                const newLastSegment = proposalParts.pop() || '';
                if (newLastSegment) {
                    proposal = expectedPrefix + '/' + newLastSegment;
                }
            }
        }

        if (hasConflict && this.value !== proposal) {
            this.hasConflict = true;
            this.conflictProposal = proposal;
            this.conflictingSlug = conflictingSlug || this.conflictingSlug || this.value;
            this.showConflictModal();
        } else {
            this.value = proposal;
            this.slugGenerated = true;
            this.clearConflictState();
            this.dispatchEvent(new CustomEvent('sluggi-change', {
                bubbles: true,
                composed: true,
                detail: { value: this.value }
            }));
            this.notifyFormEngineOfChange();
        }
    }

    private getParentPath(slug: string): string {
        const parts = slug.split('/').filter(Boolean);
        parts.pop();
        return parts.length > 0 ? '/' + parts.join('/') : '';
    }

    async sendSlugProposal(mode: 'auto' | 'recreate' | 'manual') {
        if (mode === 'recreate' && !this.syncFeatureEnabled && this.isSynced) {
            this.syncAnimating = true;
        }

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

        if (this.lastSegmentOnly && !this.isFullPathMode) {
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

        const shouldAutoSync = this.isSynced || (!this.syncFeatureEnabled && this.command === 'new');
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
            syncInput.classList.add('has-change');
            syncInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // =========================================================================
    // Private Helpers: Lock Feature
    // =========================================================================

    private toggleLock() {
        this.isLocked = !this.isLocked;
        this.notifyLockFieldOfChange();
    }

    private notifyLockFieldOfChange() {
        const lockInput = this.parentElement?.querySelector('.sluggi-lock-field') as HTMLInputElement | null;
        if (lockInput) {
            lockInput.value = this.isLocked ? '1' : '0';
            lockInput.classList.add('has-change');
            lockInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // =========================================================================
    // Private Helpers: Full Path Feature
    // =========================================================================

    private toggleFullPath() {
        this.isFullPathMode = !this.isFullPathMode;
        this.notifyFullPathFieldOfChange();
    }

    private notifyFullPathFieldOfChange() {
        const fullPathInput = this.parentElement?.querySelector('.sluggi-full-path-field') as HTMLInputElement | null;
        if (fullPathInput) {
            fullPathInput.value = this.isFullPathMode ? '1' : '0';
            fullPathInput.classList.add('has-change');
            fullPathInput.dispatchEvent(new Event('change', { bubbles: true }));
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
        this.hasSourceFields = this.sourceFieldElements.size > 0;
    }

    private observeSourceFieldInitialization() {
        const uninitializedFields = document.querySelectorAll('[data-sluggi-source]:not([data-formengine-input-initialized])');
        if (uninitializedFields.length === 0) return;

        this.sourceFieldObserver = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-formengine-input-initialized') {
                    const target = mutation.target as HTMLElement;
                    if (target.hasAttribute('data-sluggi-source')) {
                        if (!this.hasSourceFields) {
                            this.setupSourceFieldListeners();
                        }
                        this.requestUpdate();
                        this.sourceFieldObserver?.disconnect();
                        this.sourceFieldObserver = null;
                        break;
                    }
                }
            }
        });

        for (const field of uninitializedFields) {
            this.sourceFieldObserver.observe(field, { attributes: true, attributeFilter: ['data-formengine-input-initialized'] });
        }
    }

    private removeSourceFieldListeners() {
        for (const element of this.sourceFieldElements.values()) {
            element.removeEventListener('change', this.boundSourceFieldHandler);
            element.removeEventListener('input', this.boundSourceFieldInitHandler);
            element.removeEventListener('formengine:input:initialized', this.boundSourceFieldInitHandler);
        }
        this.sourceFieldElements.clear();
        this.hasSourceFields = false;
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
            hiddenInput.classList.add('has-change');
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
            const trimPattern = new RegExp(`^${fallbackEscaped}+|${fallbackEscaped}+$`, 'g');
            value = value
                .split('/')
                .map(segment => segment.replace(trimPattern, ''))
                .join('/');
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
