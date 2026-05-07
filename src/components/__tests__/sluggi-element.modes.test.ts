import '../sluggi-element.js';
import { fixture, html, expect, enterEditMode } from './helpers.js';
import type { SluggiElement } from './helpers.js';
import Notification from '../../__mocks__/typo3-notification.js';

describe('SluggiElement - Modes', () => {
    describe('Last Segment Only Mode', () => {
        it('splits value into prefix and last segment', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/parent/child/page" last-segment-only></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-prefix')?.textContent).to.contain('/parent/child');
            expect(el.shadowRoot!.querySelector('.sluggi-editable')?.textContent?.trim()).to.equal('/page');
        });

        it('does not duplicate prefix when editing multiple times', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/lorem/ipsum/dolor-sit" last-segment-only></sluggi-element>
            `);

            const input1 = await enterEditMode(el);
            input1.value = 'amet-page';
            input1.dispatchEvent(new Event('input', { bubbles: true }));
            input1.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/lorem/ipsum/amet-page');

            const input2 = await enterEditMode(el);
            input2.value = 'consectetur';
            input2.dispatchEvent(new Event('input', { bubbles: true }));
            input2.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/lorem/ipsum/consectetur');
        });

        it('does not duplicate prefix when lastSegmentOnly set dynamically', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/lorem/ipsum/dolor-sit"></sluggi-element>
            `);

            el.setAttribute('last-segment-only', '');
            await el.updateComplete;

            const input1 = await enterEditMode(el);
            input1.value = 'amet-page';
            input1.dispatchEvent(new Event('input', { bubbles: true }));
            input1.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/lorem/ipsum/amet-page');

            const input2 = await enterEditMode(el);
            input2.value = 'consectetur';
            input2.dispatchEvent(new Event('input', { bubbles: true }));
            input2.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/lorem/ipsum/consectetur');
        });

        it('handles external prefix combined with lastSegmentOnly', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element prefix="/site" value="/lorem/ipsum/dolor-sit" last-segment-only></sluggi-element>
            `);

            const input1 = await enterEditMode(el);
            input1.value = 'amet-page';
            input1.dispatchEvent(new Event('input', { bubbles: true }));
            input1.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/lorem/ipsum/amet-page');

            const input2 = await enterEditMode(el);
            input2.value = 'consectetur';
            input2.dispatchEvent(new Event('input', { bubbles: true }));
            input2.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/lorem/ipsum/consectetur');
        });

        it('setProposal keeps prefix visible for new page with single-segment value', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent-section"
                    locked-prefix="/parent-section"
                    last-segment-only
                    command="new"
                ></sluggi-element>
            `);

            el.setProposal('/parent-section/new-test-page');
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-prefix')).to.exist;
            expect(el.value).to.equal('/parent-section/new-test-page');
        });

        it('shows out-of-sync indicator on prefix when slug does not match hierarchy', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/wrong-prefix/child"
                    locked-prefix="/parent"
                    last-segment-only
                ></sluggi-element>
            `);

            const prefix = el.shadowRoot!.querySelector('.sluggi-prefix');
            expect(prefix?.textContent).to.equal('/wrong-prefix');
            expect(prefix?.classList.contains('is-out-of-sync')).to.be.true;
            expect(el.shadowRoot!.querySelector('.sluggi-editable')?.textContent?.trim()).to.equal('/child');
        });

        it('editing out-of-sync slug preserves actual parent path', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/wrong-prefix/child"
                    locked-prefix="/parent"
                    last-segment-only
                ></sluggi-element>
            `);

            const input = await enterEditMode(el);
            input.value = 'fixed-child';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/wrong-prefix/fixed-child');
        });

        it('shows mismatch note below the field when prefix does not match hierarchy', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/wrong-prefix/child"
                    locked-prefix="/parent"
                    last-segment-only
                ></sluggi-element>
            `);

            const note = el.shadowRoot!.querySelector('.sluggi-note');
            expect(note).to.exist;
            expect(note?.textContent?.toLowerCase()).to.include('custom url path');
        });

        it('does not show mismatch note when prefix matches hierarchy', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent/child"
                    locked-prefix="/parent"
                    last-segment-only
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-note')).to.be.null;
        });

        it('does not show out-of-sync indicator when locked with mismatch', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/short-url/page"
                    locked-prefix="/parent"
                    last-segment-only
                    is-locked
                    lock-feature-enabled
                ></sluggi-element>
            `);

            const prefix = el.shadowRoot!.querySelector('.sluggi-prefix');
            expect(prefix?.classList.contains('is-out-of-sync')).to.not.be.true;
        });

        it('does not show out-of-sync indicator when synced with mismatch', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/wrong-prefix/child"
                    locked-prefix="/parent"
                    last-segment-only
                    is-synced
                    sync-feature-enabled
                ></sluggi-element>
            `);

            const prefix = el.shadowRoot!.querySelector('.sluggi-prefix');
            expect(prefix?.classList.contains('is-out-of-sync')).to.not.be.true;
        });

        it('hiddenInputValue preserves mismatched prefix instead of silently rewriting', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/wrong-prefix/child"
                    locked-prefix="/parent"
                    last-segment-only
                ></sluggi-element>
            `);

            expect((el as any).hiddenInputValue).to.equal('/wrong-prefix/child');
            expect((el as any).hiddenInputValue).to.not.equal('/parent/child');
        });

        it('setProposal does not activate fullPathMode without fullPathFeatureEnabled even when prefix differs', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/short-url"
                    locked-prefix="/parent-section"
                    last-segment-only
                ></sluggi-element>
            `);

            el.setProposal('/different-section/short-url-page');
            await el.updateComplete;

            expect((el as any).isFullPathMode).to.be.false;
            expect(el.value).to.equal('/different-section/short-url-page');
        });

        it('setProposal does not activate fullPathMode for out-of-sync slug when proposal matches hierarchy', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/broken-prefix/nested-page"
                    locked-prefix="/parent-section"
                    last-segment-only
                ></sluggi-element>
            `);

            el.setProposal('/parent-section/nested-page');
            await el.updateComplete;

            expect((el as any).isFullPathMode).to.be.false;
            expect(el.value).to.equal('/parent-section/nested-page');
        });

        it('setProposal does not activate fullPathMode when shortened URL proposal matches hierarchy', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/short-url"
                    locked-prefix="/parent-section"
                    last-segment-only
                    full-path-feature-enabled
                ></sluggi-element>
            `);

            el.setProposal('/parent-section/short-url-page');
            await el.updateComplete;

            expect((el as any).isFullPathMode).to.be.false;
            expect(el.value).to.equal('/parent-section/short-url-page');
        });

        it('setProposal activates fullPathMode when proposal prefix differs from lockedPrefix', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/short-url"
                    locked-prefix="/parent-section"
                    last-segment-only
                    full-path-feature-enabled
                ></sluggi-element>
            `);

            el.setProposal('/different-section/short-url-page');
            await el.updateComplete;

            expect((el as any).isFullPathMode).to.be.true;
        });
    });

    describe('Locked Prefix (Hierarchy Permission)', () => {
        it('setProposal does not activate fullPathMode when proposal stays within locked prefix', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/organization/department/institute/about-us"
                    locked-prefix="/organization/department"
                ></sluggi-element>
            `);

            el.setProposal('/organization/department/institute/about-us-updated');
            await el.updateComplete;

            expect((el as any).isFullPathMode).to.be.false;
            expect(el.value).to.equal('/organization/department/institute/about-us-updated');
            expect(el.shadowRoot!.querySelector('.sluggi-prefix')?.textContent).to.contain('/organization/department');
        });

        it('setProposal activates fullPathMode when proposal leaves locked prefix', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/organization/department/institute/about-us"
                    locked-prefix="/organization/department"
                    full-path-feature-enabled
                ></sluggi-element>
            `);

            el.setProposal('/different-root/institute/about-us-updated');
            await el.updateComplete;

            expect((el as any).isFullPathMode).to.be.true;
        });

        it('setProposal preserves multi-segment editable part with locked prefix', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/root/sub/deep/page"
                    locked-prefix="/root"
                ></sluggi-element>
            `);

            el.setProposal('/root/sub/deep/page-renamed');
            await el.updateComplete;

            expect((el as any).isFullPathMode).to.be.false;
            expect(el.value).to.equal('/root/sub/deep/page-renamed');
        });
    });

    describe('Last Segment Only + Locked Prefix combined', () => {
        it('setProposal does not activate fullPathMode when proposal stays within locked prefix', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/organization/department/institute/about-us"
                    locked-prefix="/organization/department"
                    last-segment-only
                ></sluggi-element>
            `);

            el.setProposal('/organization/department/institute/about-us-updated');
            await el.updateComplete;

            expect((el as any).isFullPathMode).to.be.false;
            expect(el.value).to.equal('/organization/department/institute/about-us-updated');
        });
    });

    describe('Prefix Mismatch Notification', () => {
        beforeEach(() => {
            Notification._reset();
            const ctor = customElements.get('sluggi-element') as any;
            ctor?.shownMismatchNotifications?.clear();
        });

        it('shows info notification with lock advice when mismatch and lock feature enabled', async () => {
            await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/wrong-prefix/child"
                    locked-prefix="/parent"
                    last-segment-only
                    lock-feature-enabled
                ></sluggi-element>
            `);

            const call = Notification._calls.find(c => c.type === 'info');
            expect(call).to.exist;
            expect(call!.message.toLowerCase()).to.include('regenerate');
            expect(call!.message.toLowerCase()).to.include('lock');
        });

        it('shows info notification with regenerate-only advice when mismatch without lock feature', async () => {
            await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/wrong-prefix/child"
                    locked-prefix="/parent"
                    last-segment-only
                ></sluggi-element>
            `);

            const call = Notification._calls.find(c => c.type === 'info');
            expect(call).to.exist;
            expect(call!.message.toLowerCase()).to.include('regenerate');
            expect(call!.message.toLowerCase()).to.include('administrator');
            expect(call!.message.toLowerCase()).to.not.include('lock');
        });

        it('does not show notification when locked with mismatch', async () => {
            await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/wrong-prefix/child"
                    locked-prefix="/parent"
                    last-segment-only
                    is-locked
                    lock-feature-enabled
                ></sluggi-element>
            `);

            expect(Notification._calls).to.have.length(0);
        });

        it('does not show notification when synced with mismatch', async () => {
            await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/wrong-prefix/child"
                    locked-prefix="/parent"
                    last-segment-only
                    is-synced
                    sync-feature-enabled
                ></sluggi-element>
            `);

            expect(Notification._calls).to.have.length(0);
        });

        it('shows notification and highlight when unlocking a page with broken prefix', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/wrong-prefix/child"
                    locked-prefix="/parent"
                    last-segment-only
                    is-locked
                    lock-feature-enabled
                ></sluggi-element>
            `);

            expect(Notification._calls).to.have.length(0);
            const prefix = el.shadowRoot!.querySelector('.sluggi-prefix');
            expect(prefix?.classList.contains('is-out-of-sync')).to.not.be.true;

            el.isLocked = false;
            await el.updateComplete;

            expect(prefix?.classList.contains('is-out-of-sync')).to.be.true;
            const call = Notification._calls.find(c => c.type === 'info');
            expect(call).to.exist;
        });

        it('shows notification when disabling sync on a page with broken prefix', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/wrong-prefix/child"
                    locked-prefix="/parent"
                    last-segment-only
                    is-synced
                    sync-feature-enabled
                ></sluggi-element>
            `);

            expect(Notification._calls).to.have.length(0);

            el.isSynced = false;
            await el.updateComplete;

            const call = Notification._calls.find(c => c.type === 'info');
            expect(call).to.exist;
        });

        it('does not show duplicate notification when unlocking twice', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/wrong-prefix/child"
                    locked-prefix="/parent"
                    last-segment-only
                    is-locked
                    lock-feature-enabled
                ></sluggi-element>
            `);

            el.isLocked = false;
            await el.updateComplete;
            el.isLocked = true;
            await el.updateComplete;
            el.isLocked = false;
            await el.updateComplete;

            const infoCalls = Notification._calls.filter(c => c.type === 'info');
            expect(infoCalls).to.have.length(1);
        });

        it('does not show notification when no mismatch', async () => {
            await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent/child"
                    locked-prefix="/parent"
                    last-segment-only
                ></sluggi-element>
            `);

            expect(Notification._calls).to.have.length(0);
        });

        it('shows notification for admin (no lastSegmentOnly) when parent-slug mismatches value', async () => {
            await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/broken-prefix/nested-page"
                    parent-slug="/parent-section"
                    lock-feature-enabled
                ></sluggi-element>
            `);

            const call = Notification._calls.find(c => c.type === 'info');
            expect(call).to.exist;
            expect(call!.message.toLowerCase()).to.include('regenerate');
            expect(call!.message.toLowerCase()).to.include('lock');
        });

        it('does not show notification for admin when parent-slug matches value prefix', async () => {
            await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent-section/nested-page"
                    parent-slug="/parent-section"
                    lock-feature-enabled
                ></sluggi-element>
            `);

            expect(Notification._calls).to.have.length(0);
        });

        it('highlights prefix inline for admin when parent-slug mismatches', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/broken-prefix/nested-page"
                    parent-slug="/parent-section"
                    lock-feature-enabled
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-prefix')).to.be.null;
            const pathSpan = el.shadowRoot!.querySelector('.sluggi-editable-path');
            expect(pathSpan).to.exist;
            expect(pathSpan?.textContent).to.equal('/broken-prefix');
            expect(pathSpan?.classList.contains('is-out-of-sync')).to.be.true;
        });

        it('does not show notification for admin when locked with parent-slug mismatch', async () => {
            await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/broken-prefix/nested-page"
                    parent-slug="/parent-section"
                    is-locked
                    lock-feature-enabled
                ></sluggi-element>
            `);

            expect(Notification._calls).to.have.length(0);
        });
    });

    describe('Hierarchy-Permission Editor Mismatch', () => {
        beforeEach(() => {
            Notification._reset();
            const ctor = customElements.get('sluggi-element') as any;
            ctor?.shownMismatchNotifications?.clear();
        });

        it('shows out-of-sync indicator for hierarchy editor when slug does not match lockedPrefix', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/broken-prefix/child"
                    locked-prefix="/parent"
                ></sluggi-element>
            `);

            const prefix = el.shadowRoot!.querySelector('.sluggi-prefix');
            expect(prefix).to.exist;
            expect(prefix?.classList.contains('is-out-of-sync')).to.be.true;
        });

        it('shows mismatch note for hierarchy editor', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/broken-prefix/child"
                    locked-prefix="/parent"
                ></sluggi-element>
            `);

            const note = el.shadowRoot!.querySelector('.sluggi-note');
            expect(note).to.exist;
            expect(note?.textContent?.toLowerCase()).to.include('custom url path');
        });

        it('shows notification for hierarchy editor with mismatch', async () => {
            await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/broken-prefix/child"
                    locked-prefix="/parent"
                ></sluggi-element>
            `);

            const call = Notification._calls.find(c => c.type === 'info');
            expect(call).to.exist;
            expect(call!.message.toLowerCase()).to.include('regenerate');
        });

        it('does not garble hiddenInputValue for hierarchy editor with mismatch', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/broken-prefix/child"
                    locked-prefix="/parent"
                ></sluggi-element>
            `);

            expect((el as any).hiddenInputValue).to.equal('/broken-prefix/child');
            expect((el as any).hiddenInputValue).to.not.equal('/parent/broken-prefix/child');
        });

        it('detects mismatch using parentSlug when value matches lockedPrefix but not parent hierarchy', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/organization/department/different"
                    locked-prefix="/organization/department"
                    parent-slug="/organization/department/institute"
                ></sluggi-element>
            `);

            const prefix = el.shadowRoot!.querySelector('.sluggi-prefix');
            expect(prefix?.classList.contains('is-out-of-sync')).to.be.true;
        });

        it('no mismatch when value matches parentSlug hierarchy even with shallower lockedPrefix', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/organization/department/institute/about-us"
                    locked-prefix="/organization/department"
                    parent-slug="/organization/department/institute"
                ></sluggi-element>
            `);

            const prefix = el.shadowRoot!.querySelector('.sluggi-prefix');
            expect(prefix?.classList.contains('is-out-of-sync')).to.not.be.true;
        });
    });

    describe('Outside Locked Hierarchy (hierarchy-permission only, no lastSegmentOnly)', () => {
        beforeEach(() => {
            Notification._reset();
            const ctor = customElements.get('sluggi-element') as any;
            ctor?.shownMismatchNotifications?.clear();
        });

        it('computedPrefix returns actual parent from value when outside hierarchy', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/competely-different/path"
                    locked-prefix="/organization/department"
                ></sluggi-element>
            `);

            expect((el as any).computedPrefix).to.equal('/competely-different');
        });

        it('editableValue returns last segment when outside hierarchy', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/competely-different/path"
                    locked-prefix="/organization/department"
                ></sluggi-element>
            `);

            expect((el as any).editableValue).to.equal('/path');
        });

        it('editing preserves actual parent path when outside hierarchy', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/competely-different/path"
                    locked-prefix="/organization/department"
                ></sluggi-element>
            `);

            const input = await enterEditMode(el);
            input.value = 'about-us';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/competely-different/about-us');
        });

        it('hiddenInputValue returns value unchanged when outside hierarchy', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/competely-different/path"
                    locked-prefix="/organization/department"
                ></sluggi-element>
            `);

            expect((el as any).hiddenInputValue).to.equal('/competely-different/path');
        });

        it('strips slashes from input when outside hierarchy', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/competely-different/path"
                    locked-prefix="/organization/department"
                ></sluggi-element>
            `);

            const input = await enterEditMode(el);
            input.value = 'some/nested';
            input.dispatchEvent(new InputEvent('input', { bubbles: true }));
            await el.updateComplete;

            expect(input.value).to.not.include('/');
        });

        it('cancels edit when segment is empty outside hierarchy', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/competely-different/path"
                    locked-prefix="/organization/department"
                ></sluggi-element>
            `);

            const input = await enterEditMode(el);
            input.value = '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            expect(el.value).to.equal('/competely-different/path');
        });

        it('single-segment slug outside hierarchy has no prefix', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/landing-page"
                    locked-prefix="/organization/department"
                ></sluggi-element>
            `);

            expect((el as any).computedPrefix).to.equal('');
            expect((el as any).editableValue).to.equal('/landing-page');
        });

        it('inside hierarchy but wrong parent keeps lockedPrefix as computedPrefix', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/organization/department/different"
                    locked-prefix="/organization/department"
                    parent-slug="/organization/department/institute"
                ></sluggi-element>
            `);

            expect((el as any).computedPrefix).to.equal('/organization/department');
        });

        it('mismatch note includes expected hierarchy path', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/competely-different/path"
                    locked-prefix="/organization/department"
                    parent-slug="/organization/department/institute"
                    labels='{"prefixMismatch.note.expected": "Hierarchy path: %s"}'
                ></sluggi-element>
            `);

            const note = el.shadowRoot!.querySelector('.sluggi-note');
            expect(note).to.exist;
            expect(note?.textContent).to.include('Hierarchy path:');
            expect(note?.textContent).to.include('/organization/department/institute/');
        });
    });

    describe('Locked page outside the user\'s hierarchy', () => {
        // A locked page whose slug points outside the user's permitted
        // hierarchy is a stuck state for restricted editors: they can't
        // regenerate (lock blocks it) and they don't have full-path-edit
        // permission. The field is shown read-only with a single "ask
        // administrator" note. The slug stays visible so the editor still
        // knows which URL the page is at.
        //
        // For users who CAN act (regenerate from sources, or full-path-edit)
        // the regular mismatch indicator and advice remain unchanged.

        beforeEach(() => {
            Notification._reset();
            const ctor = customElements.get('sluggi-element') as any;
            ctor?.shownMismatchNotifications?.clear();
        });

        it('still renders the slug (no hiding)', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/admin-secret-area/secret-page"
                    locked-prefix="/restricted-section"
                    last-segment-only
                    is-locked
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.innerHTML).to.include('/admin-secret-area/secret-page');
        });

        it('marks the editable span as non-interactive', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/admin-secret-area/secret-page"
                    locked-prefix="/restricted-section"
                    last-segment-only
                    is-locked
                ></sluggi-element>
            `);

            const editable = el.shadowRoot!.querySelector('.sluggi-editable');
            expect(editable?.getAttribute('role')).to.not.equal('button');
            expect(editable?.getAttribute('tabindex')).to.equal('-1');
        });

        it('hides edit and regenerate buttons', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/admin-secret-area/secret-page"
                    locked-prefix="/restricted-section"
                    last-segment-only
                    is-locked
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-edit-btn')).to.not.exist;
            expect(el.shadowRoot!.querySelector('.sluggi-regenerate-btn')).to.not.exist;
        });

        it('shows the "ask administrator" note', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/admin-secret-area/secret-page"
                    locked-prefix="/restricted-section"
                    last-segment-only
                    is-locked
                    labels='{"customPath.note": "Custom URL path — please ask an administrator to change it."}'
                ></sluggi-element>
            `);

            const note = el.shadowRoot!.querySelector('.sluggi-custom-path-note');
            expect(note).to.exist;
            expect(note?.textContent).to.include('administrator');
        });

        it('shows only the custom-path note (no stacked lock or mismatch advice)', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/admin-secret-area/secret-page"
                    locked-prefix="/restricted-section"
                    last-segment-only
                    is-locked
                ></sluggi-element>
            `);

            // Generic "URL path is locked" or "regenerate to fix" advice would
            // either duplicate or contradict the "ask administrator" message.
            const notes = el.shadowRoot!.querySelectorAll('.sluggi-note');
            expect(notes.length).to.equal(1);
            expect(notes[0].classList.contains('sluggi-custom-path-note')).to.be.true;
        });

        it('does not show the amber prefix-mismatch highlight', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/admin-secret-area/secret-page"
                    locked-prefix="/restricted-section"
                    last-segment-only
                    is-locked
                ></sluggi-element>
            `);

            const prefix = el.shadowRoot!.querySelector('.sluggi-prefix');
            expect(prefix?.classList.contains('is-out-of-sync')).to.not.be.true;
        });

        it('preserves the original slug value (no save-side corruption)', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/admin-secret-area/secret-page"
                    locked-prefix="/restricted-section"
                    last-segment-only
                    is-locked
                ></sluggi-element>
            `);

            expect((el as any).hiddenInputValue).to.equal('/admin-secret-area/secret-page');
        });

        it('does not trigger for admins (no lockedPrefix)', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/admin-secret-area/secret-page"></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-custom-path-note')).to.not.exist;
        });

        it('does not trigger when value is inside lockedPrefix', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/restricted-section/synced-page"
                    locked-prefix="/restricted-section"
                    last-segment-only
                    is-locked
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-custom-path-note')).to.not.exist;
        });

        it('does not trigger when the user can regenerate from sources', async () => {
            // Title source field present → user has a recourse → existing
            // mismatch advice applies, not the "ask administrator" note.
            const container = document.createElement('div');
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][1][title]');
            titleInput.value = 'Some Title';
            container.appendChild(titleInput);

            const slug = document.createElement('sluggi-element') as SluggiElement;
            slug.setAttribute('value', '/admin-secret-area/secret-page');
            slug.setAttribute('locked-prefix', '/restricted-section');
            slug.setAttribute('last-segment-only', '');
            slug.setAttribute('record-id', '1');
            container.appendChild(slug);

            document.body.appendChild(container);
            await slug.updateComplete;

            expect(slug.shadowRoot!.querySelector('.sluggi-custom-path-note')).to.not.exist;
            expect(slug.shadowRoot!.querySelector('.sluggi-edit-btn')).to.exist;

            container.remove();
        });

        it('does not trigger when full-path-edit is available', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/admin-secret-area/secret-page"
                    locked-prefix="/restricted-section"
                    last-segment-only
                    full-path-feature-enabled
                ></sluggi-element>
            `);

            // The "Edit full path" button is the affordance for these users.
            expect(el.shadowRoot!.querySelector('.sluggi-custom-path-note')).to.not.exist;
        });
    });

    describe('Locked State', () => {
        it('prevents editing when locked without toggle access', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test" is-locked></sluggi-element>
            `);

            const editable = el.shadowRoot!.querySelector('.sluggi-editable') as HTMLElement;
            editable?.click();
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('input.sluggi-input')).to.not.exist;
            expect(el.shadowRoot!.querySelector('.sluggi-edit-btn')).to.be.null;
            expect(el.shadowRoot!.querySelector('.sluggi-controls')?.children.length).to.equal(0);
        });

        it('shows disabled edit button when locked with toggle access', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test" is-locked lock-feature-enabled></sluggi-element>
            `);

            const editable = el.shadowRoot!.querySelector('.sluggi-editable') as HTMLElement;
            editable?.click();
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('input.sluggi-input')).to.not.exist;
            const editBtn = el.shadowRoot!.querySelector('.sluggi-edit-btn') as HTMLButtonElement;
            expect(editBtn).to.exist;
            expect(editBtn.disabled).to.be.true;
            expect(editBtn.classList.contains('is-disabled')).to.be.true;
        });
    });

    describe('Synced State Without Toggle Access', () => {
        it('triggers auto-sync on source field change when isSynced even without syncFeatureEnabled', async () => {
            const titleInput = document.createElement('input');
            titleInput.setAttribute('data-sluggi-source', '');
            titleInput.setAttribute('data-formengine-input-name', 'data[pages][456][title]');
            titleInput.value = 'Initial Title';
            document.body.appendChild(titleInput);

            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/initial-title"
                    table-name="pages"
                    record-id="456"
                    command="edit"
                    is-synced
                ></sluggi-element>
            `);

            expect(el.syncFeatureEnabled).to.be.false;
            expect(el.isSynced).to.be.true;

            titleInput.value = 'New Title';

            const eventPromise = new Promise<CustomEvent>(resolve => {
                el.addEventListener('sluggi-request-proposal', (e) => resolve(e as CustomEvent), { once: true });
            });

            setTimeout(() => titleInput.dispatchEvent(new Event('change')));

            const event = await eventPromise;
            expect(event.detail.mode).to.equal('recreate');

            document.body.removeChild(titleInput);
        });
    });

    describe('Completely Readonly State', () => {
        it('shows full path without prefix split when locked without toggle access', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent/child/page"
                    locked-prefix="/parent"
                    is-locked
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-prefix')).to.be.null;
            const editable = el.shadowRoot!.querySelector('.sluggi-editable');
            expect(editable?.textContent?.trim()).to.equal('/parent/child/page');
        });

        it('shows full path without prefix split when synced without toggle access', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent/child/page"
                    locked-prefix="/parent"
                    is-synced
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-prefix')).to.be.null;
            const editable = el.shadowRoot!.querySelector('.sluggi-editable');
            expect(editable?.textContent?.trim()).to.equal('/parent/child/page');
        });

        it('shows full path without prefix split in last-segment-only mode when locked without toggle', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent/child/page"
                    last-segment-only
                    is-locked
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-prefix')).to.be.null;
            const editable = el.shadowRoot!.querySelector('.sluggi-editable');
            expect(editable?.textContent?.trim()).to.equal('/parent/child/page');
        });
    });
});
