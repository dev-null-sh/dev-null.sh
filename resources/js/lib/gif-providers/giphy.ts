import { isBlockedGifText, isSafeGiphyRating } from './safety.ts';
import type {
    GifKeyValidation,
    GifProvider,
    GifResult,
    GifSearchPage,
} from './types.ts';
import { GifRateLimited } from './types.ts';

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

const pageSize = 24;
const baseUrl = 'https://api.giphy.com/v1/gifs';

const params = (key: string, offset: string | null): URLSearchParams =>
    new URLSearchParams({
        api_key: key,
        limit: String(pageSize),
        offset: offset ?? '0',
        rating: 'g',
    });

const normalize = (item: GiphyItem): GifResult | null => {
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

async function page(
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
        .map(normalize)
        .filter((result): result is GifResult => result !== null);

    return {
        results,
        nextCursor:
            data.data.length < pageSize
                ? null
                : String(Number.parseInt(options.cursor ?? '0', 10) + pageSize),
    };
}

async function validateKey(key: string): Promise<GifKeyValidation> {
    const search = new URLSearchParams({
        api_key: key,
        limit: '1',
        offset: '0',
        rating: 'g',
    });

    let response: Response;

    try {
        response = await fetch(`${baseUrl}/trending?${search}`);
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
        const search = params(key, options.cursor);
        search.set('q', query);

        return page(`${baseUrl}/search?${search}`, options);
    },
    trending: (key, options) =>
        page(`${baseUrl}/trending?${params(key, options.cursor)}`, options),
    validateKey,
};
