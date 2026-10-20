/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement } from 'react';
import { beforeEach, test, vi } from 'vite-plus/test';

type Props = Record<string, unknown>;
type Element = ReactElement<Props>;

const states: unknown[] = [];
const refs: { current: unknown }[] = [];
let cursor = 0;
const visit = vi.fn();

vi.doMock('react', () => ({
    ...React,
    useState: (initial: unknown) => {
        const index = cursor++;

        if (!(index in states)) {
            states[index] = initial;
        }

        return [
            states[index],
            (update: unknown) => {
                states[index] =
                    typeof update === 'function'
                        ? (update as (current: unknown) => unknown)(
                              states[index],
                          )
                        : update;
            },
        ];
    },
    useRef: (initial: unknown) => {
        const index = cursor++;
        refs[index] ??= { current: initial };

        return refs[index];
    },
}));
vi.doMock('@inertiajs/react', () => ({ router: { visit } }));
vi.doMock('sonner', () => ({
    toast: Object.assign(vi.fn(), { success: vi.fn(), error: vi.fn() }),
}));
vi.doMock('@/actions/App/Http/Controllers/CommitPinController', () => ({
    default: {
        update: (commit: { id: string }) => `update:${commit.id}`,
        destroy: (commit: { id: string }) => `destroy:${commit.id}`,
    },
}));
vi.doMock('@/hooks/use-deleted-resources', () => ({
    useCommitNotFound: () => () => false,
}));
vi.doMock('@/lib/throttle', () => ({ toastWhenThrottled: () => false }));

const { usePinCommit } =
    await import('../../resources/js/components/pin-commit-button');

const pinned = { id: 'pinned' } as never;
const other = { id: 'other' } as never;

function render(pinnedCommit: unknown) {
    cursor = 0;

    return usePinCommit(pinnedCommit as never);
}

function pinButtonProps(pinnedCommit: unknown, commit: unknown): Props {
    const element = render(pinnedCommit).button(commit as never) as Element;

    return element.props;
}

function clickPin(pinnedCommit: unknown, commit: unknown, opener = {}): void {
    const { onClick } = pinButtonProps(pinnedCommit, commit) as {
        onClick: (event: unknown) => void;
    };

    onClick({ currentTarget: opener });
}

function confirmation(pinnedCommit: unknown, commit: unknown): Element | null {
    return render(pinnedCommit).confirmation(commit as never) as Element | null;
}

beforeEach(() => {
    vi.clearAllMocks();
    states.length = 0;
    refs.length = 0;
});

test('pins right away when nothing is pinned yet', () => {
    clickPin(null, other);

    assert.equal(visit.mock.calls.length, 1);
    assert.equal(visit.mock.calls[0][0], 'update:other');
    assert.equal(confirmation(null, other), null);
});

test('unpins the pinned commit right away', () => {
    clickPin(pinned, pinned);

    assert.equal(visit.mock.calls.length, 1);
    assert.equal(visit.mock.calls[0][0], 'destroy:pinned');
    assert.equal(confirmation(pinned, pinned), null);
});

test('asks before replacing an existing pin, under that commit only', () => {
    clickPin(pinned, other);

    assert.equal(visit.mock.calls.length, 0);
    assert.ok(confirmation(pinned, other));
    assert.equal(confirmation(pinned, pinned), null);
});

test('replacing the pin pins the new commit and closes the question', () => {
    clickPin(pinned, other);

    const { onConfirm } = confirmation(pinned, other)!.props as {
        onConfirm: () => void;
    };
    onConfirm();

    assert.equal(visit.mock.calls.length, 1);
    assert.equal(visit.mock.calls[0][0], 'update:other');
    assert.equal(confirmation(pinned, other), null);
});

test('cancelling keeps the pin and returns focus to the pin button', () => {
    const focus = vi.fn();
    clickPin(pinned, other, { focus });

    const { onCancel } = confirmation(pinned, other)!.props as {
        onCancel: () => void;
    };
    onCancel();

    assert.equal(visit.mock.calls.length, 0);
    assert.equal(focus.mock.calls.length, 1);
    assert.equal(confirmation(pinned, other), null);
});

test('drops the question once nothing is pinned anymore', () => {
    clickPin(pinned, other);

    assert.ok(confirmation(pinned, other));
    assert.equal(confirmation(null, other), null);
});

test('the pin button is busy only for the commit being saved', () => {
    clickPin(null, other);

    const { onStart } = visit.mock.calls[0][1] as { onStart: () => void };
    onStart();

    assert.equal(pinButtonProps(null, other).isSaving, true);
    assert.equal(pinButtonProps(null, pinned).isSaving, false);
});
