/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { OrgMember, User } from '../../resources/js/types';

let state: unknown[];
let cursor = 0;
let effects: (() => unknown)[];
const fetchMock = vi.fn();

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
    usePage: () => ({ props: { repository: 'dev-null-sh/dev-null.sh' } }),
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
    username: string,
    name = username,
    followed = false,
    suggested = true,
): OrgMember {
    return {
        user: { id: username, username, name, lane: 1 } as User,
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
function usernames(fields: Element[]) {
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
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    vi.stubGlobal('document', { body: {} });
});

test('suggests loaded org members immediately without scheduling a search request', () => {
    const fields = render([member('taylor'), member('jess', 'Jess', true)]);

    assert.deepEqual(usernames(fields), ['jess', 'taylor']);
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

    assert.deepEqual(usernames(render(members, 'TAY')), [
        'tay',
        'tay-f',
        'tay-a',
        'tay-b',
        'tay-c',
        'tay-z',
    ]);
    assert.deepEqual(
        usernames(
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

    assert.deepEqual(usernames(fields), ['newcomer']);
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
    assert.deepEqual(usernames(render([], 'tay')), []);
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
