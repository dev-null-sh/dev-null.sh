import assert from 'node:assert/strict';
import { test, vi } from 'vite-plus/test';
import { fetchGuilds } from '../../resources/js/lib/guilds';

test('fetches only the requested guild badges and forwards cancellation', async () => {
    const guilds = [{ handle: 'rust', name: 'Rust', emoji: '🦀', icon_url: null }];
    const fetch = vi.fn(async () => Response.json({ guilds }));
    const controller = new AbortController();
    vi.stubGlobal('fetch', fetch);

    try {
        assert.deepEqual(await fetchGuilds({ handles: ['rust', 'jobs'] }, controller.signal), guilds);
        assert.deepEqual(fetch.mock.calls, [[
            '/search/guilds?handles%5B%5D=rust&handles%5B%5D=jobs',
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal },
        ]]);
    } finally {
        vi.unstubAllGlobals();
    }
});

test('guild suggestions send the query rather than fetching a catalogue', async () => {
    const fetch = vi.fn(async (_url: string) => Response.json({ guilds: [] }));
    vi.stubGlobal('fetch', fetch);

    try {
        assert.deepEqual(await fetchGuilds({ q: 'ru' }), []);
        assert.equal(fetch.mock.calls[0]?.[0], '/search/guilds?q=ru');
    } finally {
        vi.unstubAllGlobals();
    }
});

test('a failed guild lookup is not mistaken for no matching guilds', async () => {
    vi.stubGlobal('fetch', async () => new Response(null, { status: 503 }));

    try {
        await assert.rejects(fetchGuilds({ handles: ['rust'] }), /Guild lookup failed: 503/);
    } finally {
        vi.unstubAllGlobals();
    }
});
