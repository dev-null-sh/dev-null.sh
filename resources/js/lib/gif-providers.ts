import type { CommitGif } from '@/types/commit';

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

const blockedTerms = [
    'porn',
    'pornography',
    'nsfw',
    'xxx',
    'hentai',
    'nudity',
    'nude',
    'naked',
    'erotic',
    'sex',
    'gore',
    'gory',
    'cocaine',
    'heroin',
    'methamphetamine',
];

const blockedPattern = new RegExp(`\\b(?:${blockedTerms.join('|')})\\b`, 'i');

export function isSafeGiphyRating(rating: string | undefined): boolean {
    return rating?.toLowerCase() === 'g';
}

export function isBlockedGifText(
    ...texts: Array<string | null | undefined>
): boolean {
    return texts.some(
        (text) => typeof text === 'string' && blockedPattern.test(text),
    );
}

type GiphyRendition = { url: string; width: string; height: string };

type GiphyItem = {
    id: string;
    title: string;
    username: string;
    rating?: string;
    url: string;
    images: Partial<
        Record<
            | 'downsized_medium'
            | 'downsized'
            | 'original'
            | 'fixed_height_small',
            GiphyRendition
        >
    >;
};

const giphyPageSize = 24;
const giphyBaseUrl = 'https://api.giphy.com/v1/gifs';

const giphyParams = (key: string, offset: string | null): URLSearchParams =>
    new URLSearchParams({
        api_key: key,
        limit: String(giphyPageSize),
        offset: offset ?? '0',
        rating: 'g',
    });

const normalizeGiphy = (item: GiphyItem): GifResult | null => {
    if (
        !isSafeGiphyRating(item.rating) ||
        isBlockedGifText(item.title, item.username)
    ) {
        return null;
    }

    const stored =
        item.images.downsized_medium ??
        item.images.downsized ??
        item.images.original;

    if (stored === undefined) {
        return null;
    }

    const preview =
        item.images.fixed_height_small ??
        item.images.downsized ??
        item.images.downsized_medium ??
        item.images.original;

    return {
        id: item.id,
        url: stored.url,
        pageUrl: item.url,
        title: item.title === '' ? null : item.title,
        username: item.username === '' ? null : item.username,
        width: Number.parseInt(stored.width, 10),
        height: Number.parseInt(stored.height, 10),
        previewUrl: preview?.url ?? null,
    };
};

async function giphyPage(
    url: string,
    options: { cursor: string | null; signal?: AbortSignal },
): Promise<GifSearchPage> {
    const response = await fetch(url, { signal: options.signal });

    if (response.status === 429) {
        throw new GifRateLimited();
    }

    if (!response.ok) {
        throw new Error(`GIPHY request failed: ${response.status}`);
    }

    const data = (await response.json()) as { data: GiphyItem[] };
    const results = data.data
        .map(normalizeGiphy)
        .filter((result): result is GifResult => result !== null);

    return {
        results,
        nextCursor:
            data.data.length < giphyPageSize
                ? null
                : String(
                      Number.parseInt(options.cursor ?? '0', 10) +
                          giphyPageSize,
                  ),
    };
}

async function validateGiphyKey(key: string): Promise<GifKeyValidation> {
    const search = new URLSearchParams({
        api_key: key,
        limit: '1',
        offset: '0',
        rating: 'g',
    });

    let response: Response;

    try {
        response = await fetch(`${giphyBaseUrl}/trending?${search}`);
    } catch {
        return 'unreachable';
    }

    if (response.ok) {
        return 'valid';
    }

    if (response.status === 401 || response.status === 403) {
        return 'invalid';
    }

    return response.status === 429 ? 'rate-limited' : 'unreachable';
}

export const giphy: GifProvider = {
    id: 'giphy',
    label: 'GIPHY',
    searchPlaceholder: 'Search GIPHY',
    attribution: { label: 'Powered By GIPHY', href: 'https://giphy.com' },
    search: (key, query, options) => {
        const search = giphyParams(key, options.cursor);
        search.set('q', query);

        return giphyPage(`${giphyBaseUrl}/search?${search}`, options);
    },
    trending: (key, options) =>
        giphyPage(
            `${giphyBaseUrl}/trending?${giphyParams(key, options.cursor)}`,
            options,
        ),
    validateKey: validateGiphyKey,
};

type KlipyMedia = { url: string; dims: [number, number] };

type KlipyItem = {
    id: string;
    title?: string;
    content_description?: string;
    itemurl: string;
    media_formats?: { gif?: KlipyMedia; tinygif?: KlipyMedia };
};

type KlipyResponse = { results?: KlipyItem[]; next?: string };

const klipyPageSize = 24;
const klipyBaseUrl = 'https://api.klipy.com/v2';

const klipyParams = (key: string, cursor: string | null): URLSearchParams => {
    const search = new URLSearchParams({
        key,
        limit: String(klipyPageSize),
        contentfilter: 'high',
    });

    if (cursor !== null) {
        search.set('pos', cursor);
    }

    return search;
};

const normalizeKlipy = (item: KlipyItem): GifResult | null => {
    if (isBlockedGifText(item.title, item.content_description)) {
        return null;
    }

    const gif = item.media_formats?.gif;

    if (gif === undefined) {
        return null;
    }

    return {
        id: item.id,
        url: gif.url,
        pageUrl: item.itemurl,
        title: item.title || item.content_description || null,
        username: null,
        width: gif.dims[0],
        height: gif.dims[1],
        previewUrl: item.media_formats?.tinygif?.url ?? gif.url,
    };
};

async function klipyPage(
    url: string,
    options: { cursor: string | null; signal?: AbortSignal },
): Promise<GifSearchPage> {
    const response = await fetch(url, { signal: options.signal });

    if (response.status === 429) {
        throw new GifRateLimited();
    }

    if (!response.ok) {
        throw new Error(`KLIPY request failed: ${response.status}`);
    }

    const data = (await response.json()) as KlipyResponse;
    const results = (data.results ?? [])
        .map(normalizeKlipy)
        .filter((result): result is GifResult => result !== null);

    return {
        results,
        nextCursor: data.next === '' ? null : (data.next ?? null),
    };
}

async function validateKlipyKey(key: string): Promise<GifKeyValidation> {
    const search = new URLSearchParams({
        key,
        limit: '8',
        contentfilter: 'high',
    });

    let response: Response;

    try {
        response = await fetch(`${klipyBaseUrl}/featured?${search}`);
    } catch {
        return 'unreachable';
    }

    if (response.ok) {
        return 'valid';
    }

    if (response.status === 401 || response.status === 403) {
        return 'invalid';
    }

    return response.status === 429 ? 'rate-limited' : 'unreachable';
}

export const klipy: GifProvider = {
    id: 'klipy',
    label: 'KLIPY',
    searchPlaceholder: 'Search KLIPY',
    attribution: { label: 'Powered by KLIPY', href: 'https://klipy.com' },
    search: (key, query, options) => {
        const search = klipyParams(key, options.cursor);
        search.set('q', query);

        return klipyPage(`${klipyBaseUrl}/search?${search}`, options);
    },
    trending: (key, options) =>
        klipyPage(
            `${klipyBaseUrl}/featured?${klipyParams(key, options.cursor)}`,
            options,
        ),
    validateKey: validateKlipyKey,
};

export const gifProviders: Record<GifProviderId, GifProvider> = {
    klipy,
    giphy,
};

export function gifProviderFor(id: GifProviderId): GifProvider {
    return gifProviders[id];
}

export function attachmentFromResult(
    id: GifProviderId,
    result: GifResult,
): GifAttachment {
    return { ...result, provider: id };
}

/** A gif attachment shaped for CommitGif to render, dropping the draft's extra previewUrl field. */
export function toCommitGif(gif: GifAttachment): CommitGif {
    return {
        provider: gif.provider,
        id: gif.id,
        url: gif.url,
        page_url: gif.pageUrl,
        title: gif.title,
        username: gif.username,
        width: gif.width,
        height: gif.height,
    };
}

export function gifFormFields(
    gif: GifAttachment,
): Record<string, string | number> {
    return {
        gif_provider: gif.provider,
        gif_id: gif.id,
        gif_url: gif.url,
        gif_page_url: gif.pageUrl,
        gif_title: gif.title ?? '',
        gif_username: gif.username ?? '',
        gif_width: gif.width,
        gif_height: gif.height,
    };
}
