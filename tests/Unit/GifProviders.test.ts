import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    attachmentFromResult,
    gifFormFields,
} from '../../resources/js/lib/gif-providers/index.ts';
import { giphy } from '../../resources/js/lib/gif-providers/giphy.ts';
import { klipy } from '../../resources/js/lib/gif-providers/klipy.ts';
import { GifRateLimited } from '../../resources/js/lib/gif-providers/types.ts';

const originalFetch = globalThis.fetch;

function stubFetch(payload: unknown, status = 200): string[] {
    const calls: string[] = [];

    globalThis.fetch = (async (input: RequestInfo | URL) => {
        calls.push(String(input));

        return new Response(JSON.stringify(payload), { status });
    }) as typeof fetch;

    return calls;
}

function restoreFetch(): void {
    globalThis.fetch = originalFetch;
}

const giphyPayload = {
    data: [{
        id: 'xT9IgzoKnwFNmISR8I',
        title: 'Deal With It GIF',
        username: 'giphy',
        rating: 'g',
        url: 'https://giphy.com/gifs/deal-with-it-xT9IgzoKnwFNmISR8I',
        images: {
            fixed_height_small: { url: 'https://media2.giphy.com/media/xT9IgzoKnwFNmISR8I/100.gif?cid=abc', width: '178', height: '100' },
            downsized_medium: { url: 'https://media2.giphy.com/media/xT9IgzoKnwFNmISR8I/giphy-downsized-medium.gif?cid=abc', width: '480', height: '270' },
        },
    }],
};

const klipyPayload = {
    results: [{
        id: 'hello-hi-662',
        title: 'Hello',
        content_description: 'hello greeting',
        itemurl: 'https://klipy.com/gifs/hello-hi-662',
        media_formats: {
            gif: { url: 'https://static.klipy.com/ii/8GCrVAB7.gif', dims: [498, 498] },
            tinygif: { url: 'https://static.klipy.com/ii/y6iepZM7.gif', dims: [220, 220] },
        },
    }],
    next: 'cursor-2',
};

test('giphy builds search and trending urls with the g rating', async () => {
    const searchCalls = stubFetch(giphyPayload);
    try {
        const page = await giphy.search('key-123', 'deal with it', { cursor: null });
        const url = new URL(searchCalls[0]!);
        assert.equal(url.origin + url.pathname, 'https://api.giphy.com/v1/gifs/search');
        assert.equal(url.searchParams.get('api_key'), 'key-123');
        assert.equal(url.searchParams.get('q'), 'deal with it');
        assert.equal(url.searchParams.get('rating'), 'g');
        assert.equal(url.searchParams.get('offset'), '0');
        assert.equal(page.results[0]?.url, 'https://media2.giphy.com/media/xT9IgzoKnwFNmISR8I/giphy-downsized-medium.gif?cid=abc');
        assert.equal(page.results[0]?.previewUrl, 'https://media2.giphy.com/media/xT9IgzoKnwFNmISR8I/100.gif?cid=abc');
        assert.equal(page.nextCursor, null);
    } finally {
        restoreFetch();
    }

    const trendingCalls = stubFetch(giphyPayload);
    try {
        await giphy.trending('key-123', { cursor: '24' });
        const trendingUrl = new URL(trendingCalls[0]!);
        assert.ok(trendingCalls[0]!.includes('/v1/gifs/trending?'));
        assert.equal(trendingUrl.searchParams.get('offset'), '24');
        assert.equal(trendingUrl.searchParams.get('rating'), 'g');
    } finally {
        restoreFetch();
    }
});

test('giphy derives the next cursor from a full page and passes it back as offset', async () => {
    const fullPage = {
        data: Array.from({ length: 24 }, (_, index) => ({
            id: `gif-${index}`,
            title: `GIF ${index}`,
            username: 'giphy',
            rating: 'g',
            url: `https://giphy.com/gifs/gif-${index}`,
            images: {
                downsized_medium: {
                    url: `https://media2.giphy.com/media/gif-${index}/giphy.gif`,
                    width: '480',
                    height: '270',
                },
            },
        })),
    };

    stubFetch(fullPage);
    try {
        const page = await giphy.search('key-123', 'deal', { cursor: null });
        assert.equal(page.nextCursor, '24');
    } finally {
        restoreFetch();
    }

    const nextCalls = stubFetch(fullPage);
    try {
        const page = await giphy.search('key-123', 'deal', { cursor: '24' });
        const url = new URL(nextCalls[0]!);
        assert.equal(url.searchParams.get('offset'), '24');
        assert.equal(page.nextCursor, '48');
    } finally {
        restoreFetch();
    }
});

test('klipy builds tenor-compatible urls and maps the next cursor', async () => {
    const calls = stubFetch(klipyPayload);
    try {
        const page = await klipy.search('k-key', 'hello', { cursor: null });
        const url = new URL(calls[0]!);
        assert.equal(url.origin + url.pathname, 'https://api.klipy.com/v2/search');
        assert.equal(url.searchParams.get('key'), 'k-key');
        assert.equal(url.searchParams.get('q'), 'hello');
        assert.equal(url.searchParams.get('contentfilter'), 'high');
        assert.equal(page.results[0]?.pageUrl, 'https://klipy.com/gifs/hello-hi-662');
        assert.equal(page.results[0]?.username, null);
        assert.equal(page.results[0]?.previewUrl, 'https://static.klipy.com/ii/y6iepZM7.gif');
        assert.equal(page.nextCursor, 'cursor-2');
    } finally {
        restoreFetch();
    }

    const nextCalls = stubFetch(klipyPayload);
    try {
        await klipy.search('k-key', 'hello', { cursor: 'cursor-2' });
        const url = new URL(nextCalls[0]!);
        assert.equal(url.searchParams.get('pos'), 'cursor-2');
    } finally {
        restoreFetch();
    }

    const trendingCalls = stubFetch({ results: [], next: '' });
    try {
        const page = await klipy.trending('k-key', { cursor: 'cursor-2' });
        const url = new URL(trendingCalls[0]!);
        assert.ok(trendingCalls[0]!.includes('/v2/featured?'));
        assert.equal(url.searchParams.get('contentfilter'), 'high');
        assert.equal(url.searchParams.get('pos'), 'cursor-2');
        assert.equal(page.nextCursor, null);
    } finally {
        restoreFetch();
    }
});

test('adapters drop items without a usable rendition', async () => {
    stubFetch({
        data: [{
            id: 'no-renditions',
            title: 'Nothing',
            username: '',
            rating: 'g',
            url: 'https://giphy.com/gifs/nothing',
            images: {},
        }],
    });
    try {
        const page = await giphy.search('key-123', 'nothing', { cursor: null });
        assert.deepEqual(page.results, []);
    } finally {
        restoreFetch();
    }

    stubFetch({
        results: [{
            id: 'no-gif',
            title: 'Nothing',
            itemurl: 'https://klipy.com/gifs/no-gif',
            media_formats: {
                tinygif: { url: 'https://static.klipy.com/ii/no-gif.gif', dims: [220, 220] },
            },
        }],
        next: '',
    });
    try {
        const page = await klipy.search('k-key', 'nothing', { cursor: null });
        assert.deepEqual(page.results, []);
    } finally {
        restoreFetch();
    }
});

const rendition = (slug: string) => ({
    url: `https://media2.giphy.com/media/${slug}.gif`,
    width: '480',
    height: '270',
});

test('giphy derives the next cursor from the raw page size after filtering', async () => {
    const safe = (index: number) => ({
        id: `gif-${index}`,
        title: `GIF ${index}`,
        username: 'giphy',
        rating: 'g',
        url: `https://giphy.com/gifs/gif-${index}`,
        images: { original: rendition(`gif-${index}`) },
    });

    stubFetch({
        data: Array.from({ length: 24 }, (_, index) =>
            index === 0
                ? { ...safe(index), rating: 'r' }
                : safe(index),
        ),
    });

    try {
        const page = await giphy.search('key-123', 'gif', { cursor: null });

        assert.equal(page.results.length, 23);
        assert.equal(page.nextCursor, '24');
    } finally {
        restoreFetch();
    }
});

test('giphy falls back across stored renditions and previews', async () => {
    const cases: {
        name: string;
        images: Record<string, ReturnType<typeof rendition>>;
        stored: string;
        preview: string;
    }[] = [
        {
            name: 'stored downsized',
            images: { downsized: rendition('downsized') },
            stored: 'https://media2.giphy.com/media/downsized.gif',
            preview: 'https://media2.giphy.com/media/downsized.gif',
        },
        {
            name: 'stored original',
            images: { original: rendition('original') },
            stored: 'https://media2.giphy.com/media/original.gif',
            preview: 'https://media2.giphy.com/media/original.gif',
        },
        {
            name: 'fixed height small preview',
            images: {
                fixed_height_small: rendition('small'),
                downsized: rendition('downsized'),
                downsized_medium: rendition('downsized-medium'),
                original: rendition('original'),
            },
            stored: 'https://media2.giphy.com/media/downsized-medium.gif',
            preview: 'https://media2.giphy.com/media/small.gif',
        },
        {
            name: 'downsized preview',
            images: {
                downsized: rendition('downsized'),
                downsized_medium: rendition('downsized-medium'),
                original: rendition('original'),
            },
            stored: 'https://media2.giphy.com/media/downsized-medium.gif',
            preview: 'https://media2.giphy.com/media/downsized.gif',
        },
        {
            name: 'downsized medium preview',
            images: {
                downsized_medium: rendition('downsized-medium'),
                original: rendition('original'),
            },
            stored: 'https://media2.giphy.com/media/downsized-medium.gif',
            preview: 'https://media2.giphy.com/media/downsized-medium.gif',
        },
        {
            name: 'original preview',
            images: { original: rendition('original') },
            stored: 'https://media2.giphy.com/media/original.gif',
            preview: 'https://media2.giphy.com/media/original.gif',
        },
    ];

    for (const { name, images, stored, preview } of cases) {
        stubFetch({
            data: [{
                id: 'gif',
                title: 'GIF',
                username: 'giphy',
                rating: 'g',
                url: 'https://giphy.com/gifs/gif',
                images,
            }],
        });

        try {
            const page = await giphy.search('key-123', 'gif', {
                cursor: null,
            });

            assert.equal(page.results[0]?.url, stored, name);
            assert.equal(page.results[0]?.previewUrl, preview, name);
        } finally {
            restoreFetch();
        }
    }
});

test('giphy normalizes empty titles and usernames to null', async () => {
    stubFetch({
        data: [{
            id: 'gif',
            title: '',
            username: '',
            rating: 'g',
            url: 'https://giphy.com/gifs/gif',
            images: { original: rendition('original') },
        }],
    });

    try {
        const page = await giphy.search('key-123', 'gif', { cursor: null });

        assert.equal(page.results[0]?.title, null);
        assert.equal(page.results[0]?.username, null);
    } finally {
        restoreFetch();
    }
});

test('giphy drops results without a safe rating', async () => {
    const item = {
        id: 'gif',
        title: 'GIF',
        username: 'giphy',
        url: 'https://giphy.com/gifs/gif',
        images: { original: rendition('original') },
    };

    for (const [rating, expected] of [
        ['g', 1],
        ['pg', 0],
        ['pg-13', 0],
        ['r', 0],
        ['y', 0],
        [undefined, 0],
    ] as const) {
        stubFetch({ data: [{ ...item, rating }] });

        try {
            const page = await giphy.search('key-123', 'gif', {
                cursor: null,
            });

            assert.equal(page.results.length, expected, String(rating));
        } finally {
            restoreFetch();
        }
    }
});

test('giphy drops blocked titles and usernames', async () => {
    const item = (id: string, title: string, username: string) => ({
        id,
        title,
        username,
        rating: 'g',
        url: `https://giphy.com/gifs/${id}`,
        images: { original: rendition(id) },
    });

    stubFetch({
        data: [
            item('blocked-title', 'PORN star', 'giphy'),
            item('blocked-username', 'Hello', 'xxx'),
            item('safe', 'Hello', 'giphy'),
        ],
    });

    try {
        const page = await giphy.search('key-123', 'hello', {
            cursor: null,
        });

        assert.deepEqual(page.results.map((result) => result.id), ['safe']);
    } finally {
        restoreFetch();
    }
});

test('klipy drops blocked titles and descriptions', async () => {
    const item = (
        id: string,
        title: string,
        description: string,
    ) => ({
        id,
        title,
        content_description: description,
        itemurl: `https://klipy.com/gifs/${id}`,
        media_formats: {
            gif: {
                url: `https://static.klipy.com/ii/${id}.gif`,
                dims: [498, 498] as [number, number],
            },
        },
    });

    stubFetch({
        results: [
            item('blocked-title', 'PORN', ''),
            item('blocked-description', 'Hello', 'gory scene'),
            item('safe', 'Hello', 'hello greeting'),
        ],
        next: '',
    });

    try {
        const page = await klipy.search('k-key', 'hello', {
            cursor: null,
        });

        assert.deepEqual(page.results.map((result) => result.id), ['safe']);
    } finally {
        restoreFetch();
    }
});

test('klipy falls back to the content description for its title', async () => {
    const cases: {
        name: string;
        title: string | undefined;
        description: string | undefined;
        expected: string | null;
    }[] = [
        {
            name: 'empty title',
            title: '',
            description: 'hello greeting',
            expected: 'hello greeting',
        },
        {
            name: 'absent title',
            title: undefined,
            description: 'hello greeting',
            expected: 'hello greeting',
        },
        {
            name: 'empty fallback',
            title: '',
            description: '',
            expected: null,
        },
        {
            name: 'absent both',
            title: undefined,
            description: undefined,
            expected: null,
        },
    ];

    for (const { name, title, description, expected } of cases) {
        stubFetch({
            results: [{
                id: 'hello-hi-662',
                title,
                content_description: description,
                itemurl: 'https://klipy.com/gifs/hello-hi-662',
                media_formats: {
                    gif: {
                        url: 'https://static.klipy.com/ii/8GCrVAB7.gif',
                        dims: [498, 498],
                    },
                },
            }],
            next: '',
        });

        try {
            const page = await klipy.search('k-key', 'hello', {
                cursor: null,
            });

            assert.equal(page.results[0]?.title, expected, name);
        } finally {
            restoreFetch();
        }
    }
});

test('klipy reuses the gif url when the tiny preview is missing', async () => {
    stubFetch({
        results: [{
            id: 'hello-hi-662',
            title: 'Hello',
            itemurl: 'https://klipy.com/gifs/hello-hi-662',
            media_formats: {
                gif: {
                    url: 'https://static.klipy.com/ii/8GCrVAB7.gif',
                    dims: [498, 498],
                },
            },
        }],
        next: '',
    });

    try {
        const page = await klipy.search('k-key', 'hello', { cursor: null });

        assert.equal(
            page.results[0]?.previewUrl,
            'https://static.klipy.com/ii/8GCrVAB7.gif',
        );
    } finally {
        restoreFetch();
    }
});

test('adapters reject non-429 http failures', async () => {
    stubFetch({}, 500);
    try {
        await assert.rejects(() => giphy.trending('k', { cursor: null }), /request failed/);
        await assert.rejects(() => klipy.trending('k', { cursor: null }), /request failed/);
    } finally {
        restoreFetch();
    }
});

test('adapters map throttling and key verdicts to the shared shapes', async () => {
    stubFetch({}, 429);
    try {
        await assert.rejects(() => giphy.trending('k', { cursor: null }), GifRateLimited);
        assert.equal(await giphy.validateKey('k'), 'rate-limited');
        assert.equal(await klipy.validateKey('k'), 'rate-limited');
    } finally {
        restoreFetch();
    }

    for (const status of [401, 403]) {
        stubFetch({}, status);
        try {
            assert.equal(await giphy.validateKey('k'), 'invalid');
            assert.equal(await klipy.validateKey('k'), 'invalid');
        } finally {
            restoreFetch();
        }
    }

    stubFetch({}, 200);
    try {
        assert.equal(await giphy.validateKey('k'), 'valid');
        assert.equal(await klipy.validateKey('k'), 'valid');
    } finally {
        restoreFetch();
    }

    globalThis.fetch = (async () => {
        throw new TypeError('network');
    }) as typeof fetch;
    try {
        assert.equal(await giphy.validateKey('k'), 'unreachable');
        assert.equal(await klipy.validateKey('k'), 'unreachable');
    } finally {
        restoreFetch();
    }
});

test('attachments carry the provider and shape the hidden form fields', () => {
    const gif = attachmentFromResult('klipy', {
        id: 'hello-hi-662',
        url: 'https://static.klipy.com/ii/8GCrVAB7.gif',
        pageUrl: 'https://klipy.com/gifs/hello-hi-662',
        title: 'Hello',
        username: null,
        width: 498,
        height: 498,
        previewUrl: 'https://static.klipy.com/ii/y6iepZM7.gif',
    });

    assert.deepEqual(gifFormFields(gif), {
        gif_provider: 'klipy',
        gif_id: 'hello-hi-662',
        gif_url: 'https://static.klipy.com/ii/8GCrVAB7.gif',
        gif_page_url: 'https://klipy.com/gifs/hello-hi-662',
        gif_title: 'Hello',
        gif_username: '',
        gif_width: 498,
        gif_height: 498,
    });
});
