/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { beforeEach, test, vi } from 'vite-plus/test';
import type { Guild } from '../../resources/js/types/guild';

let state: unknown[];
let cursor = 0;
const errors = { setError: vi.fn(), clearErrors: vi.fn() };
vi.doMock('react', () => ({
    ...React,
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

const { Form } = await import('@inertiajs/react');
const { GuildDialog } =
    await import('../../resources/js/components/guild-dialog');
const { GuildPreferences } =
    await import('../../resources/js/components/guild-preferences');
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
    vi.clearAllMocks();
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
