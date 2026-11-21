/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { beforeEach, test, vi } from 'vite-plus/test';
import type { Commit } from '../../resources/js/types';
import type { Guild } from '../../resources/js/types/guild';

let state: unknown[];
let cursor = 0;
let effects: (() => void)[] = [];
const errors = { setError: vi.fn(), clearErrors: vi.fn() };
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({
        props: {
            guilds: [
                {
                    id: 'guild-id',
                    handle: 'rust',
                    aliases: [],
                    name: 'Rust',
                    is_joined: true,
                },
            ],
            auth: { isAdmin: false },
        },
    }),
}));
vi.doMock('../../resources/js/layouts/app-layout', () => ({
    default: () => null,
}));
vi.doMock('react', () => ({
    ...React,
    useEffect: (effect: () => void) => {
        effects.push(effect);
    },
    useLayoutEffect: () => {},
    useRef: () => ({ current: errors }),
    useState: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) state[index] = initial;
        return [
            state[index],
            (value: unknown) => {
                state[index] = value;
            },
        ];
    },
}));
vi.doMock('../../resources/js/components/follow-button', () => ({
    useFollowHover: () => ({
        isJustToggled: false,
        markToggled: vi.fn(),
        onMouseLeave: vi.fn(),
    }),
}));

const { Form, router } = await import('@inertiajs/react');
const { default: GuildBoard } =
    await import('../../resources/js/pages/guild/show');
const { CommitCard } =
    await import('../../resources/js/components/commit-card');
const { GuildAliasesButton } =
    await import('../../resources/js/components/guild-aliases');
const { GuildDialog } =
    await import('../../resources/js/components/guild-dialog');
const { GuildPreferences } =
    await import('../../resources/js/components/guild-preferences');
const { default: GuildIndex } =
    await import('../../resources/js/pages/guild/index');
const { ProfileGuilds } =
    await import('../../resources/js/components/profile-guilds');
const { Dialog, DialogClose } =
    await import('../../resources/js/components/ui/dialog');
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
function dialog(handle?: string) {
    cursor = 0;
    const form = elements(GuildDialog({ handle, children: 'Create' })).find(
        (element) => element.type === Form,
    )!;
    const fields = elements(
        (form.props.children as (props: unknown) => ReactNode)({
            processing: false,
            errors: {},
        }),
    );
    return { form, fields };
}
beforeEach(() => {
    state = [];
    cursor = 0;
    effects = [];
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    vi.clearAllMocks();
});

test('promotes a separate pin without changing loaded chronological rows and restores its natural position on unpin', () => {
    const commits = Array.from(
        { length: 60 },
        (_, index) => ({ id: String(index) }) as Commit,
    );
    const old = { id: 'old' } as Commit;
    const boardIds = (pinnedCommit: Commit | null) => {
        cursor = 0;
        return elements(
            GuildBoard({
                handle: 'rust',
                readFrom: null,
                canModerate: true,
                commits,
                pinnedCommit,
                page: 2,
                hasMore: true,
            }),
        )
            .filter((element) => element.type === CommitCard)
            .map((element) => (element.props.commit as Commit).id);
    };

    assert.deepEqual(boardIds(old), [
        'old',
        ...commits.map((commit) => commit.id),
    ]);
    assert.deepEqual(boardIds(commits[40]), [
        '40',
        ...commits
            .filter((commit) => commit.id !== '40')
            .map((commit) => commit.id),
    ]);
    assert.deepEqual(
        boardIds(null),
        commits.map((commit) => commit.id),
    );
    assert.equal(commits.length, 60);
});

test('clears the missed commits of a joined guild when its board opens', () => {
    const put = vi.fn();
    const optimistic = vi
        .spyOn(router, 'optimistic')
        .mockReturnValue({ put } as unknown as typeof router);

    GuildBoard({
        handle: 'rust',
        readFrom: null,
        canModerate: false,
        commits: [],
        pinnedCommit: null,
        page: 1,
        hasMore: false,
    });
    effects.forEach((effect) => effect());

    const clear = optimistic.mock.calls[0][0] as (props: {
        joinedGuilds: { handle: string; missed_commits: number }[];
    }) => unknown;
    assert.deepEqual(
        clear({
            joinedGuilds: [
                { handle: 'rust', missed_commits: 4 },
                { handle: 'php', missed_commits: 2 },
            ],
        }),
        {
            joinedGuilds: [
                { handle: 'rust', missed_commits: 0 },
                { handle: 'php', missed_commits: 2 },
            ],
        },
    );
    assert.equal(put.mock.calls[0][0], '/guild-reads/guild-id');
    assert.deepEqual(put.mock.calls[0][2].only, ['joinedGuilds']);
});

test('removes an alias optimistically from its own guild only', () => {
    const remove = vi.fn();
    const optimistic = vi
        .spyOn(router, 'optimistic')
        .mockReturnValue({ delete: remove } as unknown as typeof router);
    const guild = {
        id: 'guild-id',
        handle: 'go',
        aliases: ['golang', 'gopher'],
    } as Guild;
    const other = { id: 'other', handle: 'rust', aliases: ['golang'] } as Guild;

    const button = elements(GuildAliasesButton({ guild })).find(
        (element) => element.props['aria-label'] === 'Remove #golang',
    )!;
    (button.props.onClick as () => void)();

    const update = optimistic.mock.calls[0][0] as (props: {
        guilds: Guild[];
    }) => unknown;
    assert.deepEqual(update({ guilds: [guild, other] }), {
        guilds: [{ id: 'guild-id', handle: 'go', aliases: ['gopher'] }, other],
    });
    assert.equal(remove.mock.calls[0][0], '/guilds/go/aliases/golang');
});

test('changes only the pin optimistically and prevents pin and load-more requests from overlapping', () => {
    const commit = { id: 'target' } as Commit;
    const view = () => {
        cursor = 0;
        return elements(
            GuildBoard({
                handle: 'rust',
                readFrom: null,
                canModerate: true,
                commits: [commit],
                pinnedCommit: null,
                page: 20,
                hasMore: true,
            }),
        );
    };
    const pinButton = () =>
        elements(
            view().find((element) => element.type === CommitCard)!.props
                .threadActions as ReactNode,
        )[0];
    const loadButton = () =>
        elements(
            view().find(
                (element) => element.props['aria-label'] === 'Guild commits',
            )!.props.children as ReactNode,
        )[0];
    vi.stubGlobal('document', { querySelectorAll: () => [] });
    const optimistic = vi.spyOn(router, 'optimistic').mockReturnValue(router);
    const put = vi.spyOn(router, 'put').mockImplementation(() => {});
    const get = vi.spyOn(router, 'get').mockImplementation(() => {});

    (pinButton().props.onClick as () => void)();
    assert.deepEqual(optimistic.mock.calls[0][0]({} as never), {
        pinnedCommit: commit,
    });
    assert.deepEqual(put.mock.calls[0][1], { pinned: true });
    assert.deepEqual(put.mock.calls[0][2]?.only, [
        'pinnedCommit',
        'canModerate',
    ]);
    assert.equal(pinButton().props.disabled, true);
    assert.equal(loadButton().props.disabled, true);
    put.mock.calls[0][2]?.onFinish?.({} as never);

    (loadButton().props.onClick as () => void)();
    assert.deepEqual(get.mock.calls[0][2]?.only, [
        'commits',
        'page',
        'hasMore',
        'pinnedCommit',
        'canModerate',
    ]);
    get.mock.calls[0][2]?.onStart?.({} as never);
    assert.equal(pinButton().props.disabled, true);
    assert.equal(loadButton().props.disabled, true);
});

test('refreshes card data with directory search and navigation so newly created guilds can render', () => {
    const view = elements(
        GuildIndex({
            query: 'rust',
            guildPage: { data: [], current_page: 2, last_page: 3, total: 50 },
        }),
    );
    const form = view.find((element) => element.type === Form)!;
    const searches = (form.props.options as { only: string[] }).only;
    const navigation = view.filter((element) =>
        Array.isArray(element.props.only),
    );

    assert.equal(navigation.length, 3);
    for (const only of [
        searches,
        ...navigation.map((element) => element.props.only as string[]),
    ]) {
        assert.ok(only.includes('guildPage'));
        assert.ok(only.includes('guilds'));
    }
});

test('keeps the search input mounted while accepting drafts and restored queries', () => {
    const input = (query: string) => {
        cursor = 0;
        const form = elements(
            GuildIndex({
                query,
                guildPage: {
                    data: [],
                    current_page: 1,
                    last_page: 1,
                    total: 0,
                },
            }),
        ).find((element) => element.type === Form)!;
        return elements(
            (form.props.children as (props: unknown) => ReactNode)({
                processing: false,
                errors: {},
            }),
        ).find((element) => element.props.name === 'q')!;
    };
    const initial = input('rust');
    (initial.props.onChange as (event: unknown) => void)({
        target: { value: 'laravel' },
    });
    assert.equal(input('rust').props.value, 'laravel');
    input('laravel');
    const submitted = input('laravel');
    assert.equal(submitted.key, initial.key);
    assert.equal(submitted.props.value, 'laravel');
    input('rust');
    const restored = input('rust');
    assert.equal(restored.key, initial.key);
    assert.equal(restored.props.value, 'rust');
});

test('cancels invalid guild creation before Inertia sends a request and accepts decorated matches', () => {
    let view = dialog('devnull');
    const name = view.fields.find((element) => element.props.name === 'name')!;
    (name.props.onChange as (event: unknown) => void)({
        target: { value: '/dev/unrelated' },
    });
    view = dialog('devnull');
    assert.equal((view.form.props.onBefore as () => boolean)(), false);
    assert.deepEqual(errors.setError.mock.calls, [
        ['name', "Guild display name doesn't match hashtag"],
    ]);
    (name.props.onChange as (event: unknown) => void)({
        target: { value: '/dev/null' },
    });
    view = dialog('devnull');
    assert.equal((view.form.props.onBefore as () => boolean)(), true);
});

test('creates from a name without submitting an empty handle and rejects unusable names', () => {
    let view = dialog();
    assert.equal(
        view.fields.some((element) => element.props.name === 'handle'),
        false,
    );
    const name = view.fields.find((element) => element.props.name === 'name')!;
    for (const [value, allowed] of [
        ['Laravel 4ever', true],
        ['!!!', false],
        ['123 Guild', false],
    ] as const) {
        (name.props.onChange as (event: unknown) => void)({
            target: { value },
        });
        view = dialog();
        assert.equal((view.form.props.onBefore as () => boolean)(), allowed);
    }
});

test('keeps the mute slot still but hides it from joined users and keyboard navigation', () => {
    for (const joined of [false, true]) {
        cursor = 0;
        const guild = {
            name: 'Rust',
            is_joined: joined,
            is_muted: false,
        } as Guild;
        const mute = elements(GuildPreferences({ guild })).find(
            (element) => element.props['aria-label'] === 'Mute Rust',
        )!;
        assert.equal(mute.props['aria-hidden'], joined);
        assert.equal(mute.props.tabIndex, joined ? -1 : undefined);
        assert.equal(
            String(mute.props.className).includes('invisible'),
            joined,
        );
    }
});

test('joins or leaves profile guilds beside their links and keeps a left guild listed until the dialog reopens', () => {
    const profileGuilds = (handles: string[]) => {
        cursor = 0;
        return elements(ProfileGuilds({ handles, handle: 'taylor' }));
    };
    const rows = (view: Element[]) =>
        view
            .filter((element) => element.type === 'li')
            .map((row) =>
                React.Children.toArray(row.props.children as ReactNode),
            );

    let view = profileGuilds(['rust']);
    const [link, preferences] = rows(view)[0] as Element[];
    assert.equal(link.type, DialogClose);
    assert.equal(preferences.type, GuildPreferences);
    assert.equal(preferences.props.showMute, false);
    const controls = elements(
        GuildPreferences(
            preferences.props as Parameters<typeof GuildPreferences>[0],
        ),
    );
    assert.ok(
        controls.every(
            (element) =>
                !String(element.props['aria-label']).startsWith('Mute'),
        ),
    );
    assert.ok(
        controls.some(
            (element) =>
                element.props.type === 'button' &&
                element.props.variant === 'outline',
        ),
    );

    const listed = (view: Element[]) =>
        view
            .filter((element) => element.type === GuildPreferences)
            .map((element) => (element.props.guild as Guild).handle);

    view = profileGuilds([]);
    assert.deepEqual(listed(view), ['rust']);

    (
        view.find((element) => element.type === Dialog)!.props.onOpenChange as (
            open: boolean,
        ) => void
    )(true);
    view = profileGuilds([]);
    assert.deepEqual(listed(view), []);
    assert.equal(rows(view).length, 1);
});
