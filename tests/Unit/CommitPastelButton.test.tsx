/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { beforeEach, test, vi } from 'vite-plus/test';

type Element = ReactElement<Record<string, unknown>>;
type Update = number | ((current: number) => number);

const day = '2026-10-16';
let now = new Date(`${day}T12:00:00Z`);
const values = new Map<string, number>();
const refs = new Map<number, unknown[]>();
let instance = 0;
let cursor = 0;
const pendings: {
    resolve: (v: unknown) => void;
    reject: (e: unknown) => void;
}[] = [];
const submit = vi.fn(
    () =>
        new Promise((resolve, reject) => {
            pendings.push({ resolve, reject });
        }),
);
const flushAll = vi.fn();
const toastError = vi.fn();
const toast = Object.assign(vi.fn(), { error: toastError });

vi.doMock('react', () => ({
    ...React,
    useRef: (initial: unknown) => {
        const index = cursor++;
        const own = refs.get(instance) ?? [];
        refs.set(instance, own);
        if (!(index in own)) {
            own[index] = { current: initial };
        }
        return own[index];
    },
}));
vi.doMock('@inertiajs/react', () => ({
    router: { flushAll },
    usePage: () => ({
        props: {
            auth: { user: { id: 'viewer' }, pasteis: { remaining: 3, day } },
        },
    }),
    useHttp: () => ({ processing: false, submit }),
}));
vi.doMock('sonner', () => ({ toast }));
vi.doMock('@/actions/App/Http/Controllers/CommitPastelController', () => ({
    store: () => 'store',
}));
vi.doMock('@/hooks/use-now', () => ({
    useNow: () => now,
}));
vi.doMock('@/lib/throttle', () => ({ toastWhenThrottled: () => false }));
vi.doMock('@/hooks/use-shared-state', () => ({
    refreshSharedState: (fresh: Map<string, number>) =>
        fresh.forEach((value, key) => values.set(key, value)),
    useSharedState: (key: string, initial: number) => [
        values.has(key) ? values.get(key) : initial,
        (update: Update) =>
            values.set(
                key,
                typeof update === 'function'
                    ? update(values.has(key) ? values.get(key)! : initial)
                    : update,
            ),
    ],
}));

const { CommitPastelButton } =
    await import('../../resources/js/components/commit-pastel-button');
const { commitStateKey } = await import('../../resources/js/lib/commit-state');

const recipient = { id: 'author', pasteis_received: 9 };

const commit = {
    id: 'commit',
    user_id: 'author',
    status: 'approved',
    pasteis_count: 5,
} as never;
const countKey = commitStateKey('pasteis_count', 'commit');
const allowanceKey = `pasteis-allowance:viewer:${day}`;

function render(which = 0): ReactNode {
    instance = which;
    cursor = 0;

    return CommitPastelButton({ commit });
}

function findClickable(node: ReactNode): Element | null {
    if (!React.isValidElement(node)) {
        return null;
    }

    const element = node as Element;

    if (typeof element.props.onClick === 'function') {
        return element;
    }

    for (const child of React.Children.toArray(
        element.props.children as ReactNode,
    )) {
        const found = findClickable(child);

        if (found) {
            return found;
        }
    }

    return null;
}

function click(which = 0): Promise<void> {
    const clickable = findClickable(render(which));

    assert.ok(clickable);

    return (clickable.props.onClick as () => Promise<void>)();
}

function count(): number {
    void render();

    return values.get(countKey) ?? 5;
}

function allowance(): number {
    void render();

    return values.get(allowanceKey) ?? 3;
}

beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('window', {
        matchMedia: () => ({ matches: true }),
    });
    values.clear();
    refs.clear();
    pendings.length = 0;
    now = new Date(`${day}T12:00:00Z`);
});

test('awarding a pastel updates the count and allowance while pending', async () => {
    const clicked = click();

    assert.equal(count(), 6);
    assert.equal(allowance(), 2);

    pendings[0].resolve({
        pasteis_count: 6,
        remaining: 2,
        day,
        recipient: { id: 'author', pasteis_received: 9 },
    });
    await clicked;
});

test('the server response settles the count, allowance and recipient', async () => {
    const clicked = click();

    pendings[0].resolve({
        pasteis_count: 8,
        remaining: 1,
        day,
        recipient: { id: 'author', pasteis_received: 9 },
    });
    await clicked;

    assert.equal(count(), 8);
    assert.equal(allowance(), 1);
    assert.equal(values.get(commitStateKey('pasteis_received', 'author')), 9);
    assert.equal(flushAll.mock.calls.length, 1);
    assert.equal(toastError.mock.calls.length, 0);
});

test('a rejected request restores the count and allowance', async () => {
    const clicked = click();

    assert.equal(count(), 6);

    pendings[0].reject(new Error('failed'));
    await clicked;

    assert.equal(count(), 5);
    assert.equal(allowance(), 3);
    assert.equal(toastError.mock.calls.length, 1);
    assert.equal(flushAll.mock.calls.length, 0);
});

test('a click while another award is pending is ignored', async () => {
    const first = click(0);
    const second = click(1);

    assert.equal(submit.mock.calls.length, 1);
    assert.equal(allowance(), 2);
    assert.equal(count(), 6);

    pendings[0].resolve({ pasteis_count: 6, remaining: 2, day, recipient });
    await first;
    await second;

    assert.equal(allowance(), 2);
    assert.equal(count(), 6);
});

test('the next award proceeds after a failed one settles', async () => {
    const first = click(0);

    pendings[0].reject(new Error('failed'));
    await first;

    const second = click(1);

    assert.equal(submit.mock.calls.length, 2);
    assert.equal(allowance(), 2);

    pendings[1].resolve({ pasteis_count: 6, remaining: 2, day, recipient });
    await second;
});

test('a network failure with no allowance left refunds nothing', async () => {
    values.set(allowanceKey, 0);

    const clicked = click();

    assert.equal(allowance(), 0);
    assert.equal(count(), 6);

    pendings[0].reject(new Error('failed'));
    await clicked;

    assert.equal(allowance(), 0);
    assert.equal(count(), 5);
    assert.equal(toastError.mock.calls.length, 1);
});

test('a refresh before a failed award keeps the server count', async () => {
    const clicked = click();

    assert.equal(count(), 6);

    values.set(countKey, 5);

    pendings[0].reject(new Error('failed'));
    await clicked;

    assert.equal(count(), 5);
    assert.equal(allowance(), 3);
});

test('a click after midnight is ignored while an earlier award is pending', async () => {
    now = new Date(`${day}T23:59:59Z`);

    const first = click(0);

    now = new Date('2026-10-17T00:00:01Z');

    const second = click(1);

    assert.equal(submit.mock.calls.length, 1);

    await second;
    pendings[0].resolve({ pasteis_count: 6, remaining: 2, day, recipient });
    await first;
});
