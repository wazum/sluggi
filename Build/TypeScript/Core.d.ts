declare namespace TYPO3 {
    export let settings: any;
    export const lang: { [key: string]: any };
    export let Modal: any;
    export let Severity: any;
}

declare module '@typo3/backend/modal' {
    const Modal: any;
    export default Modal;
}

declare module '@typo3/core/event/debounce-event' {
    import RegularEvent from '@typo3/core/event/regular-event';

    export type Listener = Function & (EventListenerWithTarget | EventListener | EventListenerObject);

    export interface EventListenerWithTarget {
        (evt: Event, target?: Element): void;
    }

    export interface DebounceEvent extends RegularEvent {
        new (eventName: string, callback: Listener, wait?: number, immediate?: boolean): DebounceEvent;

        delegateTo: (e: any, t: any) => void;
    }

    const DebounceEvent: DebounceEvent;
    export default DebounceEvent;
}
