class ImmediateAction {
    constructor(public readonly callback: () => Promise<void> | void) {}
}

export default ImmediateAction;
