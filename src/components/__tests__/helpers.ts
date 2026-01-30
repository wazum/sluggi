import { fixture, html, expect, oneEvent } from '@open-wc/testing';
import type { SluggiElement } from '../sluggi-element.js';

export { fixture, html, expect, oneEvent };
export type { SluggiElement };

export async function enterEditMode(el: SluggiElement): Promise<HTMLInputElement> {
    const editable = el.shadowRoot!.querySelector('.sluggi-editable') as HTMLElement;
    editable.click();
    await el.updateComplete;
    return el.shadowRoot!.querySelector('input.sluggi-input') as HTMLInputElement;
}

export async function createElementWithSourceField(
    sourceValue: string,
    elementAttrs: string = ''
): Promise<{ container: HTMLDivElement; element: SluggiElement }> {
    const container = document.createElement('div');
    container.innerHTML = `
        <input data-sluggi-source data-formengine-input-name="data[pages][123][title]" value="${sourceValue}" />
        <sluggi-element value="/test" record-id="123" ${elementAttrs}></sluggi-element>
    `;
    document.body.appendChild(container);
    const element = container.querySelector('sluggi-element') as SluggiElement;
    await element.updateComplete;
    return { container, element };
}

export function cleanup(container: HTMLElement): void {
    document.body.removeChild(container);
}
