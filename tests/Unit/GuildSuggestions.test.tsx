/// <reference types="node" />
import assert from 'node:assert/strict';
import type { ReactNode } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, test, vi } from 'vite-plus/test';
import type { Guild, SuggestedGuild } from '../../resources/js/types/guild';

let props: {
    guilds: Guild[] | null;
    suggestedGuilds?: SuggestedGuild[] | null;
} = { guilds: null };

vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props }),
    Deferred: ({
        data,
        fallback,
        children,
    }: {
        data: keyof typeof props;
        fallback: ReactNode;
        children: ReactNode;
    }) => (props[data] === undefined ? fallback : children),
}));

const { GuildSuggestions, GuildSuggestionRow, reasonFor } =
    await import('../../resources/js/components/guild-suggestions');
const { TooltipProvider } =
    await import('../../resources/js/components/ui/tooltip');
const { formatExactCount } = await import('../../resources/js/lib/numbers');

afterEach(() => {
    props = { guilds: null };
});

function guild(handle: string, overrides: Partial<Guild> = {}): Guild {
    return {
        id: `${handle}-id`,
        handle,
        name: handle.toUpperCase(),
        description: `All about ${handle}`,
        emoji: '💻',
        icon_url: null,
        member_count: 128,
        is_joined: false,
        is_muted: false,
        is_paused: false,
        ...overrides,
    };
}

function render(): string {
    return renderToStaticMarkup(
        <TooltipProvider>
            <GuildSuggestions />
        </TooltipProvider>,
    );
}

test('lists the suggested guilds in order, with why each is suggested', () => {
    props = {
        guilds: [guild('laravel'), guild('rust'), guild('adhd')],
        suggestedGuilds: [
            { id: 'rust-id', followed_members_count: 2 },
            { id: 'adhd-id', followed_members_count: 0 },
        ],
    };

    const html = render();

    assert.match(html, /Guilds for you/);
    assert.equal(html.match(/data-test="suggested-guild"/g)?.length, 2);
    assert.ok(html.indexOf('#rust') < html.indexOf('#adhd'));
    assert.ok(!html.includes('#laravel'));
    assert.match(html, /Joined by 2 you follow/);
    assert.match(html, /128 members/);
    assert.match(html, /href="\/guilds\/rust"/);
    assert.match(html, /href="\/guilds"/);
});

test('says why in short numbers, exact ones on hover, and counts one member', () => {
    const suggestion = { id: 'big-id', followed_members_count: 12_345 };

    assert.equal(
        reasonFor(guild('big'), suggestion),
        'Joined by 12.3k you follow',
    );
    assert.equal(
        reasonFor(guild('big'), suggestion, formatExactCount),
        'Joined by 12,345 you follow',
    );
    assert.equal(
        reasonFor(guild('one', { member_count: 1 }), {
            id: 'one-id',
            followed_members_count: 0,
        }),
        '1 member',
    );
});

test('shows a placeholder for each guild it can suggest until the suggestions load', () => {
    for (const [guilds, placeholders] of [
        [[guild('a'), guild('b'), guild('c'), guild('d')], 3],
        [[guild('a'), guild('b', { is_joined: true }), guild('c')], 2],
        [
            [
                guild('a'),
                guild('b', { is_muted: true }),
                guild('c', { is_paused: true }),
            ],
            1,
        ],
    ] as const) {
        props = { guilds: [...guilds] };

        const html = render();

        assert.match(html, /Guilds for you/);
        assert.equal(
            html.match(/<li aria-hidden="true">/g)?.length,
            placeholders,
        );
    }
});

test('shows nothing without the guild preview, or with nothing to suggest', () => {
    for (const shared of [
        { guilds: null },
        { guilds: [] },
        {
            guilds: [
                guild('joined', { is_joined: true }),
                guild('muted', { is_muted: true }),
                guild('paused', { is_paused: true }),
            ],
        },
        { guilds: [guild('laravel')], suggestedGuilds: null },
        { guilds: [guild('laravel')], suggestedGuilds: [] },
        {
            guilds: [guild('laravel')],
            suggestedGuilds: [{ id: 'gone-id', followed_members_count: 1 }],
        },
    ]) {
        props = shared;

        assert.equal(render(), '');
    }
});

test('drops a suggestion muted or paused since it loaded, but keeps one joined', () => {
    props = {
        guilds: [
            guild('joined', { is_joined: true }),
            guild('muted', { is_muted: true }),
            guild('paused', { is_paused: true }),
        ],
        suggestedGuilds: [
            { id: 'joined-id', followed_members_count: 0 },
            { id: 'muted-id', followed_members_count: 0 },
            { id: 'paused-id', followed_members_count: 0 },
        ],
    };

    const html = render();

    assert.equal(html.match(/data-test="suggested-guild"/g)?.length, 1);
    assert.match(html, /aria-label="Leave JOINED"/);
});

test('offers to join a suggested guild, and to leave it once joined', () => {
    const laravel = guild('laravel');
    const suggestion = { id: laravel.id, followed_members_count: 0 };
    const row = (item: Guild) =>
        renderToStaticMarkup(
            <TooltipProvider>
                <GuildSuggestionRow guild={item} suggestion={suggestion} />
            </TooltipProvider>,
        );

    assert.match(row(laravel), /aria-label="Join LARAVEL"/);
    assert.match(
        row({ ...laravel, is_joined: true }),
        /aria-label="Leave LARAVEL"/,
    );
    assert.ok(!row(laravel).includes('aria-pressed'));
});

test('gives long names and handles their full text on hover', () => {
    const long = guild('a'.repeat(60), { name: 'A'.repeat(60) });
    const html = renderToStaticMarkup(
        <TooltipProvider>
            <GuildSuggestionRow
                guild={long}
                suggestion={{ id: long.id, followed_members_count: 0 }}
            />
        </TooltipProvider>,
    );

    assert.match(html, new RegExp(`title="${'A'.repeat(60)}"`));
    assert.match(html, new RegExp(`title="#${'a'.repeat(60)}"`));
});
