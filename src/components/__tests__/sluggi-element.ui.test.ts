import '../sluggi-element.js';
import { fixture, html, expect, enterEditMode } from './helpers.js';
import type { SluggiElement } from './helpers.js';

describe('SluggiElement - UI', () => {
    describe('Accessibility', () => {
        it('has proper ARIA attributes for keyboard navigation', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element value="/test"></sluggi-element>
            `);

            const editable = el.shadowRoot!.querySelector('.sluggi-editable');
            expect(editable?.getAttribute('role')).to.equal('button');
            expect(editable?.getAttribute('tabindex')).to.equal('0');

            const input = await enterEditMode(el);
            expect(input.getAttribute('aria-label')).to.exist;
        });
    });

    describe('Restriction Notes', () => {
        it('shows sync restriction note when isSynced', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    is-synced
                ></sluggi-element>
            `);

            const note = el.shadowRoot!.querySelector('.sluggi-note');
            expect(note).to.exist;
            expect(note?.textContent).to.contain('synchronized');
            expect(note?.getAttribute('role')).to.equal('status');
            expect(note?.getAttribute('aria-live')).to.equal('polite');
        });

        it('shows lock restriction note when isLocked', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    is-locked
                ></sluggi-element>
            `);

            const note = el.shadowRoot!.querySelector('.sluggi-note');
            expect(note).to.exist;
            expect(note?.textContent).to.contain('locked');
        });

        it('does not show restriction note when neither synced nor locked', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                ></sluggi-element>
            `);

            expect(el.shadowRoot!.querySelector('.sluggi-note')).to.be.null;
        });

        it('shows full path info note when full path edit mode is active', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/parent/child"
                    last-segment-only
                    full-path-feature-enabled
                ></sluggi-element>
            `);

            const fullPathEditBtn = el.shadowRoot!.querySelector('.sluggi-full-path-edit-btn') as HTMLElement;
            fullPathEditBtn.click();
            await el.updateComplete;

            const note = el.shadowRoot!.querySelector('.sluggi-note');
            expect(note).to.exist;
            expect(note?.textContent).to.contain('Full path editing');
        });
    });

    describe('Collapsed Controls', () => {
        it('shows burger menu trigger when collapsed-controls is set', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger');
            expect(menuTrigger).to.exist;
        });

        it('hides burger menu trigger when collapsed-controls is not set', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger');
            expect(menuTrigger).to.be.null;
        });

        it('expands controls on mouse enter', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const wrapper = el.shadowRoot!.querySelector('.sluggi-wrapper') as HTMLElement;
            wrapper.dispatchEvent(new MouseEvent('mouseenter'));
            await el.updateComplete;

            const collapsedMenu = el.shadowRoot!.querySelector('.sluggi-collapsed-menu');
            expect(collapsedMenu?.classList.contains('expanded')).to.be.true;
        });

        it('retracts controls after timeout on mouse leave', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const wrapper = el.shadowRoot!.querySelector('.sluggi-wrapper') as HTMLElement;
            wrapper.dispatchEvent(new MouseEvent('mouseenter'));
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-collapsed-menu.expanded')).to.exist;

            wrapper.dispatchEvent(new MouseEvent('mouseleave'));
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-collapsed-menu.expanded')).to.exist;

            await new Promise(resolve => setTimeout(resolve, 2100));
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-collapsed-menu.expanded')).to.be.null;
        });

        it('cancels retract timeout when mouse re-enters', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const wrapper = el.shadowRoot!.querySelector('.sluggi-wrapper') as HTMLElement;
            wrapper.dispatchEvent(new MouseEvent('mouseenter'));
            await el.updateComplete;

            wrapper.dispatchEvent(new MouseEvent('mouseleave'));
            await el.updateComplete;

            await new Promise(resolve => setTimeout(resolve, 1000));

            wrapper.dispatchEvent(new MouseEvent('mouseenter'));
            await el.updateComplete;

            await new Promise(resolve => setTimeout(resolve, 1500));
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-collapsed-menu.expanded')).to.exist;
        });

        it('always shows controls directly in edit mode', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const editBtn = el.shadowRoot!.querySelector('.sluggi-edit-btn') as HTMLElement;
            editBtn.click();
            await el.updateComplete;

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger');
            expect(menuTrigger).to.be.null;

            const saveBtn = el.shadowRoot!.querySelector('.sluggi-save-btn');
            expect(saveBtn).to.exist;
        });

        it('menu trigger is a button with proper ARIA attributes', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger') as HTMLButtonElement;
            expect(menuTrigger.tagName).to.equal('BUTTON');
            expect(menuTrigger.getAttribute('aria-expanded')).to.equal('false');
            expect(menuTrigger.getAttribute('aria-haspopup')).to.equal('true');
            expect(menuTrigger.getAttribute('aria-label')).to.exist;
        });

        it('expands controls on Enter key press', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger') as HTMLButtonElement;
            menuTrigger.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            await el.updateComplete;

            const collapsedMenu = el.shadowRoot!.querySelector('.sluggi-collapsed-menu');
            expect(collapsedMenu?.classList.contains('expanded')).to.be.true;
            expect(menuTrigger.getAttribute('aria-expanded')).to.equal('true');
        });

        it('expands controls on Space key press', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger') as HTMLButtonElement;
            menuTrigger.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', bubbles: true }));
            await el.updateComplete;

            const collapsedMenu = el.shadowRoot!.querySelector('.sluggi-collapsed-menu');
            expect(collapsedMenu?.classList.contains('expanded')).to.be.true;
        });

        it('closes menu and focuses trigger on Escape from menu content', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger') as HTMLButtonElement;
            menuTrigger.click();
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-collapsed-menu.expanded')).to.exist;

            const wrapper = el.shadowRoot!.querySelector('.sluggi-wrapper') as HTMLElement;
            wrapper.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            await el.updateComplete;

            expect(el.shadowRoot!.querySelector('.sluggi-collapsed-menu.expanded')).to.be.null;
        });

        it('menu content has inert attribute when collapsed', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuContent = el.shadowRoot!.querySelector('.sluggi-menu-content') as HTMLElement;
            expect(menuContent.hasAttribute('inert')).to.be.true;
        });

        it('menu content does not have inert attribute when expanded', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger') as HTMLButtonElement;
            menuTrigger.click();
            await el.updateComplete;

            const menuContent = el.shadowRoot!.querySelector('.sluggi-menu-content') as HTMLElement;
            expect(menuContent.hasAttribute('inert')).to.be.false;
        });

        it('menu trigger has tabindex -1 when expanded', async () => {
            const el = await fixture<SluggiElement>(html`
                <sluggi-element
                    value="/test"
                    collapsed-controls
                ></sluggi-element>
            `);

            const menuTrigger = el.shadowRoot!.querySelector('.sluggi-menu-trigger') as HTMLButtonElement;
            expect(menuTrigger.getAttribute('tabindex')).to.equal('0');

            menuTrigger.click();
            await el.updateComplete;

            expect(menuTrigger.getAttribute('tabindex')).to.equal('-1');
        });
    });
});
