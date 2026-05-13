class DeferredAction {
    constructor(public readonly callback: () => Promise<void>) {}
}

export default DeferredAction;
