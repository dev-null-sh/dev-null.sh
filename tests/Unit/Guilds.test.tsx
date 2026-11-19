import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { beforeEach, test, vi } from 'vite-plus/test';
import { fetchGuilds } from '../../resources/js/lib/guilds';

let state: unknown[];
let cursor = 0;
let effects: (() => unknown)[];
let guildsEnabled = true;
vi.doMock('react', () => ({
    ...React,
    useEffect: (effect: () => unknown) => effects.push(effect),
    useState: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) state[index] = initial;
        return [
            state[index],
            (value: unknown) => {
                state[index] =
                    typeof value === 'function' ? value(state[index]) : value;
            },
        ];
    },
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { guildsEnabled } }),
}));
vi.doMock('../../resources/js/layouts/app-layout', () => ({
    default: () => null,
}));

const { HashtagGuild } = await import('../../resources/js/pages/search/index');
const { GuildSearchHeading } =
    await import('../../resources/js/components/commit-search-results');
const { GuildDialog } =
    await import('../../resources/js/components/guild-dialog');
const { GuildIcon } = await import('../../resources/js/components/guild-icon');
type Element = ReactElement<Record<string, unknown>>;
function elements(node: ReactNode): Element[] {
    if (!React.isValidElement(node)) return [];
    const element = node as Element;
    return [
        element,
        ...React.Children.toArray(element.props.children as ReactNode).flatMap(
            elements,
        ),
    ];
}
function banner(term = '#rust', searchAttempt = 0) {
    const props = {
        term,
        searchAttempt,
        initial: { handle: 'rust', guild: null },
    };
    cursor = 0;
    effects = [];
    HashtagGuild(props);
    cursor = 0;
    effects = [];
    return HashtagGuild(props);
}
beforeEach(() => {
    state = [];
    cursor = 0;
    effects = [];
    guildsEnabled = true;
});

test('uses the server lookup for first paint only, and rechecks after edits or a new search', async () => {
    vi.useFakeTimers();
    const fetch = vi.fn().mockResolvedValue(Response.json({ guilds: [] }));
    vi.stubGlobal('fetch', fetch);
    try {
        assert.ok(
            elements(banner()).some((element) => element.type === GuildDialog),
        );
        assert.equal(effects[0](), undefined);
        banner('');
        const pending = banner();
        assert.equal(pending?.props['aria-busy'], true);
        assert.equal(
            elements(pending).some((element) => element.type === GuildDialog),
            false,
        );
        effects[0]();
        await vi.advanceTimersByTimeAsync(120);
        assert.ok(
            elements(banner()).some((element) => element.type === GuildDialog),
        );
        assert.equal(banner('#rust', 1)?.props['aria-busy'], true);
    } finally {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    }
});

test('does not trust a disabled initial lookup and retries a failed exact lookup without editing', async () => {
    vi.useFakeTimers();
    const fetch = vi
        .fn()
        .mockRejectedValueOnce(new Error('offline'))
        .mockResolvedValue(Response.json({ guilds: [] }));
    vi.stubGlobal('fetch', fetch);
    try {
        guildsEnabled = false;
        assert.equal(banner(), null);
        guildsEnabled = true;
        assert.equal(banner()?.props['aria-busy'], true);
        effects[0]();
        await vi.advanceTimersByTimeAsync(120);
        const failed = banner();
        assert.equal(
            elements(failed).some((element) => element.type === GuildDialog),
            false,
        );
        const retry = elements(failed).find(
            (element) => element.props.children === 'Retry',
        )!;
        (retry.props.onClick as () => void)();
        assert.equal(banner()?.props['aria-busy'], true);
        effects[0]();
        await vi.advanceTimersByTimeAsync(120);
        assert.ok(
            elements(banner()).some((element) => element.type === GuildDialog),
        );
        assert.equal(fetch.mock.calls.length, 2);
    } finally {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    }
});

test('hides protected guild metadata in search headings when guilds are disabled', () => {
    guildsEnabled = false;
    const fields = elements(
        GuildSearchHeading({
            handle: 'rust',
            guild: {
                handle: 'rust',
                name: 'Rust',
                emoji: '🦀',
                icon_url: '/protected-icon',
            },
        }),
    );
    assert.deepEqual(
        fields.find((element) => element.type === GuildIcon)?.props.guild,
        { emoji: '#', icon_url: null },
    );
    assert.ok(fields.some((element) => element.props.children === '#rust'));
});

test('does not turn an aborted exact lookup into a missing guild or an error', async () => {
    vi.useFakeTimers();
    const request = Promise.withResolvers<Response>();
    vi.stubGlobal('fetch', vi.fn().mockReturnValue(request.promise));
    try {
        banner('#jobs');
        const cleanup = effects[0]() as () => void;
        await vi.advanceTimersByTimeAsync(120);
        cleanup();
        request.reject(new Error('cancelled'));
        await vi.advanceTimersByTimeAsync(0);
        const fields = elements(banner('#jobs'));
        assert.equal(
            fields.some((element) => element.type === GuildDialog),
            false,
        );
        assert.equal(
            fields.some(
                (element) => element.props.children === 'Guild lookup failed.',
            ),
            false,
        );
        assert.equal(banner('#jobs')?.props['aria-busy'], true);
    } finally {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    }
});

test('fetches only the requested guild badges and forwards cancellation', async () => {
    const guilds = [
        { handle: 'rust', name: 'Rust', emoji: '🦀', icon_url: null },
    ];
    const fetch = vi.fn(async () => Response.json({ guilds }));
    const controller = new AbortController();
    vi.stubGlobal('fetch', fetch);

    try {
        assert.deepEqual(
            await fetchGuilds({ handles: ['rust', 'jobs'] }, controller.signal),
            guilds,
        );
        assert.deepEqual(fetch.mock.calls, [
            [
                '/search/guilds?handles%5B%5D=rust&handles%5B%5D=jobs',
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: controller.signal,
                },
            ],
        ]);
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
        await assert.rejects(
            fetchGuilds({ handles: ['rust'] }),
            /Guild lookup failed: 503/,
        );
    } finally {
        vi.unstubAllGlobals();
    }
});
