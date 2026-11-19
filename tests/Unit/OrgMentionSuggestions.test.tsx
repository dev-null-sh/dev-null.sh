/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { OrgMember, User } from '../../resources/js/types';
import type { GuildBadge } from '../../resources/js/types/guild';

let state: unknown[];
let cursor = 0;
let effects: (() => unknown)[];
const fetchMock = vi.fn();
let guildsEnabled = false;

vi.doMock('react', () => ({
    ...React,
    useId: () => 'mentions',
    useRef: () => ({ current: null }),
    useEffectEvent: (callback: unknown) => callback,
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
vi.doMock('react-dom', () => ({
    createPortal: (children: unknown) => children,
}));
vi.doMock('@inertiajs/react', () => ({
    usePage: () => ({
        props: { repository: 'dev-null-sh/dev-null.sh', guildsEnabled },
    }),
}));
vi.doMock('../../resources/js/components/user-avatar', () => ({
    UserAvatar: () => null,
}));

const { MentionTextarea } =
    await import('../../resources/js/components/mention-textarea');

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
function member(
    handle: string,
    name = handle,
    followed = false,
    suggested = true,
): OrgMember {
    return {
        user: { id: handle, handle, name, lane: 1 } as User,
        role: 'contributor',
        read_through_id: 0,
        is_followed: followed,
        suggests_mentions: suggested,
    };
}
function render(members: OrgMember[] | undefined, query = '') {
    cursor = 0;
    effects = [];
    state[0] = { kind: 'mention', start: 0, query };
    return elements(
        MentionTextarea({
            value: `@${query}`,
            onValueChange: () => {},
            org: members === undefined ? undefined : 'org',
            members,
        }),
    );
}
function handles(fields: Element[]) {
    return fields
        .filter(
            (element) => element.props['data-test'] === 'mention-suggestion',
        )
        .map((element) =>
            String(element.props.id).replace('mentions-mention-', ''),
        );
}

beforeEach(() => {
    state = [undefined, { top: 0, left: 0 }];
    effects = [];
    guildsEnabled = false;
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    vi.stubGlobal('document', { body: {} });
});

test('fetches matching guilds on demand and inserts a hex-shaped hashtag', async () => {
    vi.useFakeTimers();
    guildsEnabled = true;
    const guilds = ['dead', 'deadbeef'].map(
        (handle) =>
            ({
                id: handle,
                handle,
                name: handle,
                emoji: '💻',
                icon_url: null,
            }) as GuildBadge,
    );
    fetchMock.mockResolvedValue({ ok: true, json: async () => ({ guilds }) });
    state[0] = { kind: 'guild', start: 6, query: 'DE' };
    cursor = 0;
    effects = [];
    const onValueChange = vi.fn();
    let fields = elements(
        MentionTextarea({ value: 'Hello #DE', onValueChange }),
    );
    assert.equal(
        fields.filter((element) => element.props.role === 'option').length,
        0,
    );
    const cleanup = effects[2]() as () => void;
    await vi.advanceTimersByTimeAsync(120);
    assert.equal(fetchMock.mock.calls.length, 1);
    assert.match(fetchMock.mock.calls[0][0], /q=de/);
    cursor = 0;
    effects = [];
    fields = elements(MentionTextarea({ value: 'Hello #DE', onValueChange }));
    const options = fields.filter((element) => element.props.role === 'option');
    assert.deepEqual(
        options.map((option) => option.props.id),
        ['mentions-guild-dead', 'mentions-guild-deadbeef'],
    );
    const textarea = fields.find((element) => element.type === 'textarea')!;
    const target = {
        selectionStart: 9,
        focus: vi.fn(),
        setSelectionRange: vi.fn(),
    };
    (textarea.props.ref as (element: unknown) => void)(target);
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) =>
        callback(),
    );
    (options[1].props.onClick as () => void)();
    assert.deepEqual(onValueChange.mock.calls, [['Hello #deadbeef ']]);
    assert.deepEqual(target.setSelectionRange.mock.calls, [[16, 16]]);
    cleanup();
});

test('does not suggest guilds inside code, URLs, partial handles or when preview data is absent', () => {
    for (const value of [
        '`#rust',
        '```\n#rust',
        'https://example.com/#rust',
        'word#rust',
        '#rust ',
        '##rust',
        'color(#dead',
    ]) {
        guildsEnabled = true;
        cursor = 0;
        state[0] = null;
        const fields = elements(
            MentionTextarea({ value, onValueChange: () => {} }),
        );
        const textarea = fields.find((element) => element.type === 'textarea')!;
        (textarea.props.onSelect as (event: unknown) => void)({
            currentTarget: {
                value,
                selectionStart: value.length,
                selectionEnd: value.length,
            },
        });
        assert.equal(state[0], null, value);
    }
    guildsEnabled = false;
    cursor = 0;
    const textarea = elements(
        MentionTextarea({ value: '#rust', onValueChange: () => {} }),
    ).find((element) => element.type === 'textarea')!;
    (textarea.props.onSelect as (event: unknown) => void)({
        currentTarget: { value: '#rust', selectionStart: 5, selectionEnd: 5 },
    });
    assert.equal(state[0], null);
});

test('dismisses pending guild suggestions without closing the editor and discards a reopened attempt', async () => {
    vi.useFakeTimers();
    guildsEnabled = true;
    fetchMock.mockResolvedValue({
        ok: true,
        json: async () => ({ guilds: [{ handle: 'rust', name: 'Rust' }] }),
    });
    state[0] = { kind: 'guild', start: 0, query: 'ru' };
    const onKeyDown = vi.fn();
    const renderGuild = () => {
        cursor = 0;
        effects = [];
        return elements(
            MentionTextarea({
                value: '#ru',
                onValueChange: () => {},
                onKeyDown,
            }),
        );
    };
    let fields = renderGuild();
    const cleanup = effects[2]() as () => void;
    await vi.advanceTimersByTimeAsync(120);
    fields = renderGuild();
    assert.equal(
        fields.filter((element) => element.props.role === 'option').length,
        1,
    );
    const escape = {
        key: 'Escape',
        nativeEvent: {},
        preventDefault: vi.fn(),
        stopPropagation: vi.fn(),
    };
    (
        fields.find((element) => element.type === 'textarea')!.props
            .onKeyDown as (event: unknown) => void
    )(escape);
    cleanup();
    state[0] = { kind: 'guild', start: 0, query: 'ru' };
    state[1] = { top: 0, left: 0 };
    fields = renderGuild();
    assert.equal(
        fields.filter((element) => element.props.role === 'option').length,
        0,
    );
    assert.equal(
        fields.find((element) => element.type === 'textarea')!.props[
            'aria-activedescendant'
        ],
        undefined,
    );
    (
        fields.find((element) => element.type === 'textarea')!.props
            .onKeyDown as (event: unknown) => void
    )(escape);
    assert.equal(onKeyDown.mock.calls.length, 0);
    assert.equal(escape.stopPropagation.mock.calls.length, 2);
});

for (const activation of ['Enter', 'pointer']) {
    test(`retries failed guild suggestions with ${activation} without changing the draft and distinguishes an empty success`, async () => {
        vi.useFakeTimers();
        guildsEnabled = true;
        fetchMock
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValue({
                ok: true,
                json: async () => ({ guilds: [] }),
            });
        state[0] = { kind: 'guild', start: 0, query: 'ru' };
        const onValueChange = vi.fn();
        const renderGuild = () => {
            cursor = 0;
            effects = [];
            return elements(MentionTextarea({ value: '#ru', onValueChange }));
        };
        renderGuild();
        effects[2]();
        await vi.advanceTimersByTimeAsync(120);
        let fields = renderGuild();
        assert.ok(
            fields.some(
                (element) =>
                    element.props.children ===
                    'Guild lookup failed. Press Enter to retry.',
            ),
        );
        if (activation === 'Enter') {
            const enter = {
                key: 'Enter',
                nativeEvent: { isComposing: false },
                preventDefault: vi.fn(),
            };
            (
                fields.find((element) => element.type === 'textarea')!.props
                    .onKeyDown as (event: unknown) => void
            )(enter);
            assert.equal(enter.preventDefault.mock.calls.length, 1);
        } else {
            const retry = fields.find(
                (element) =>
                    element.type === 'button' &&
                    element.props.children === 'Retry',
            )!;
            (retry.props.onClick as () => void)();
        }
        renderGuild();
        effects[2]();
        await vi.advanceTimersByTimeAsync(120);
        fields = renderGuild();
        assert.ok(
            fields.some(
                (element) => element.props.children === 'No matching guilds.',
            ),
        );
        assert.equal(
            fields.some(
                (element) =>
                    element.props.children ===
                    'Guild lookup failed. Press Enter to retry.',
            ),
            false,
        );
        assert.equal(onValueChange.mock.calls.length, 0);
        assert.equal(fetchMock.mock.calls.length, 2);
    });
}

test('keeps cancelled guild suggestions silent and ignores their late responses', async () => {
    vi.useFakeTimers();
    guildsEnabled = true;
    for (const failed of [false, true]) {
        const request = Promise.withResolvers<Response>();
        fetchMock.mockReturnValue(request.promise);
        state[0] = { kind: 'guild', start: 0, query: 'ru' };
        state[1] = { top: 0, left: 0 };
        cursor = 0;
        effects = [];
        MentionTextarea({ value: '#ru', onValueChange: () => {} });
        const cleanup = effects[2]() as () => void;
        await vi.advanceTimersByTimeAsync(120);
        cleanup();
        if (failed) {
            request.reject(new Error('cancelled'));
        } else {
            request.resolve(Response.json({ guilds: [{ handle: 'rust' }] }));
        }
        await vi.advanceTimersByTimeAsync(0);
        cursor = 0;
        const fields = elements(
            MentionTextarea({ value: '#ru', onValueChange: () => {} }),
        );
        assert.equal(
            fields.some((element) => element.props.role === 'option'),
            false,
        );
        assert.equal(
            fields.some(
                (element) =>
                    element.props.children ===
                    'Guild lookup failed. Press Enter to retry.',
            ),
            false,
        );
    }
});

test('suggests loaded org members immediately without scheduling a search request', () => {
    const fields = render([member('taylor'), member('jess', 'Jess', true)]);

    assert.deepEqual(handles(fields), ['jess', 'taylor']);
    assert.equal(effects[0](), undefined);
    assert.equal(fetchMock.mock.calls.length, 0);
});

test('preserves matching tiers, followed priority, exclusions, and the six-result cap', () => {
    const members = [
        member('tay-z'),
        member('tay-a'),
        member('tay-f', 'Followed', true),
        member('tay'),
        member('name-prefix', 'Taylor'),
        member('contains', 'A Taylor'),
        member('tay-hidden', 'Taylor', true, false),
        member('tay-b'),
        member('tay-c'),
        member('irrelevant'),
    ];

    assert.deepEqual(handles(render(members, 'TAY')), [
        'tay',
        'tay-f',
        'tay-a',
        'tay-b',
        'tay-c',
        'tay-z',
    ]);
    assert.deepEqual(
        handles(
            render(
                [
                    member('contains', 'A Taylor'),
                    member('name-prefix', 'Taylor'),
                    member('substring', 'Different'),
                ],
                'tay',
            ),
        ),
        ['name-prefix', 'contains'],
    );
});

test('updates an open picker when members join or leave and keeps keyboard selection in bounds', () => {
    render([member('alex'), member('jess'), member('taylor')]);
    state[3] = 2;
    const fields = render([member('newcomer')]);

    assert.deepEqual(handles(fields), ['newcomer']);
    assert.equal(
        fields.find((element) => element.type === 'textarea')?.props[
            'aria-activedescendant'
        ],
        'mentions-mention-newcomer',
    );
    assert.equal(
        fields.find((element) => element.props.role === 'option')?.props[
            'aria-selected'
        ],
        true,
    );
    assert.deepEqual(handles(render([], 'tay')), []);
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

test('still searches the server outside orgs', async () => {
    vi.useFakeTimers();
    fetchMock.mockResolvedValue({
        ok: true,
        json: async () => ({ users: [] }),
    });
    render(undefined, 'tay');
    const cleanup = effects[0]() as () => void;

    await vi.advanceTimersByTimeAsync(120);

    assert.equal(fetchMock.mock.calls.length, 1);
    assert.match(fetchMock.mock.calls[0][0], /q=tay/);
    cleanup();
});
