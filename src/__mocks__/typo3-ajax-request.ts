interface MockResponse {
    resolve(): Promise<unknown>;
}

class AjaxRequest {
    constructor(public readonly url: string) {}

    withQueryArguments(_params: Record<string, unknown>): this {
        return this;
    }

    async get(): Promise<MockResponse> {
        return { resolve: async () => ({ status: 'ok', title: 'ok', message: 'ok' }) };
    }

    async post(): Promise<MockResponse> {
        return { resolve: async () => ({ status: 'ok', title: 'ok', message: 'ok' }) };
    }
}

export default AjaxRequest;
