/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { OrgMember, User } from '../../resources/js/types';
import type { Guild } from '../../resources/js/types/guild';

let state: unknown[];
let cursor = 0;
let effects: (() => unknown)[];
const fetchMock = vi.fn();
let guilds: Guild[] | null = null;

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
        props: { repository: 'dev-null-sh/dev-null.sh', guilds },
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
    guilds = null;
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    vi.stubGlobal('document', { body: {} });
});

test('suggests loaded guilds and inserts the selected hashtag without fetching', () => {
    guilds = ['rust', 'rustlang', 'laravel'].map(
        (handle) =>
            ({
                id: handle,
                handle,
                name: handle,
                emoji: '💻',
                icon_url: null,
            }) as Guild,
    );
    state[0] = { kind: 'guild', start: 6, query: 'RU' };
    cursor = 0;
    effects = [];
    const onValueChange = vi.fn();
    const fields = elements(
        MentionTextarea({ value: 'Hello #RU', onValueChange }),
    );
    const options = fields.filter((element) => element.props.role === 'option');
    assert.deepEqual(
        options.map((option) => option.props.id),
        ['mentions-guild-rust', 'mentions-guild-rustlang'],
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
    assert.deepEqual(onValueChange.mock.calls, [['Hello #rustlang ']]);
    assert.deepEqual(target.setSelectionRange.mock.calls, [[16, 16]]);
    effects.slice(0, 2).forEach((effect) => effect());
    assert.equal(fetchMock.mock.calls.length, 0);
});

test('does not suggest guilds inside code, URLs, partial handles or when preview data is absent', () => {
    for (const value of [
        '`#rust',
        '```\n#rust',
        'https://example.com/#rust',
        'word#rust',
        '#rust ',
        '##rust',
    ]) {
        guilds = [{ id: 'rust', handle: 'rust', name: 'Rust' } as Guild];
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
    guilds = null;
    cursor = 0;
    const textarea = elements(
        MentionTextarea({ value: '#rust', onValueChange: () => {} }),
    ).find((element) => element.type === 'textarea')!;
    (textarea.props.onSelect as (event: unknown) => void)({
        currentTarget: { value: '#rust', selectionStart: 5, selectionEnd: 5 },
    });
    assert.equal(state[0], null);
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
