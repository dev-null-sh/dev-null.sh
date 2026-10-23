import { isBlockedGifText } from './safety.ts';
import type {
    GifKeyValidation,
    GifProvider,
    GifResult,
    GifSearchPage,
} from './types.ts';
import { GifRateLimited } from './types.ts';

type KlipyMedia = { url: string; dims: [number, number] };

type KlipyItem = {
    id: string;
    title?: string;
    content_description?: string;
    itemurl: string;
    media_formats?: { gif?: KlipyMedia; tinygif?: KlipyMedia };
};

type KlipyResponse = { results?: KlipyItem[]; next?: string };

const pageSize = 24;
const baseUrl = 'https://api.klipy.com/v2';

const params = (key: string, cursor: string | null): URLSearchParams => {
    const search = new URLSearchParams({
        key,
        limit: String(pageSize),
        contentfilter: 'high',
    });

    if (cursor !== null) {
        search.set('pos', cursor);
    }

    return search;
};

const normalize = (item: KlipyItem): GifResult | null => {
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

async function page(
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
        .map(normalize)
        .filter((result): result is GifResult => result !== null);

    return {
        results,
        nextCursor: data.next === '' ? null : (data.next ?? null),
    };
}

async function validateKey(key: string): Promise<GifKeyValidation> {
    const search = new URLSearchParams({
        key,
        limit: '8',
        contentfilter: 'high',
    });

    let response: Response;

    try {
        response = await fetch(`${baseUrl}/featured?${search}`);
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
        const search = params(key, options.cursor);
        search.set('q', query);

        return page(`${baseUrl}/search?${search}`, options);
    },
    trending: (key, options) =>
        page(`${baseUrl}/featured?${params(key, options.cursor)}`, options),
    validateKey,
};
