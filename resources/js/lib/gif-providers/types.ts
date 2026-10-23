export type GifProviderId = 'giphy' | 'klipy';

export type GifResult = {
    id: string;
    url: string;
    pageUrl: string;
    title: string | null;
    username: string | null;
    width: number;
    height: number;
    previewUrl: string | null;
};

export type GifAttachment = GifResult & { provider: GifProviderId };

export type GifSearchPage = {
    results: GifResult[];
    nextCursor: string | null;
};

export type GifKeyValidation =
    | 'valid'
    | 'invalid'
    | 'rate-limited'
    | 'unreachable';

export class GifRateLimited extends Error {
    constructor() {
        super('The GIF provider is rate limiting searches.');
    }
}

export interface GifProvider {
    readonly id: GifProviderId;
    readonly label: string;
    readonly searchPlaceholder: string;
    readonly attribution: { readonly label: string; readonly href: string };
    search(
        key: string,
        query: string,
        options: { cursor: string | null; signal?: AbortSignal },
    ): Promise<GifSearchPage>;
    trending(
        key: string,
        options: { cursor: string | null; signal?: AbortSignal },
    ): Promise<GifSearchPage>;
    validateKey(key: string): Promise<GifKeyValidation>;
}
