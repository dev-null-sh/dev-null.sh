import assert from 'node:assert/strict';
import type { ReactElement } from 'react';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { fakeConnection, joinAll } from './FakeRealtime';
import { hookHarness } from './HookHarness';

const harness = hookHarness();
vi.doMock('react', async () => ({
    ...(await vi.importActual('react')),
    ...harness.react,
    useCallback: (callback: unknown, deps: unknown[]) => {
        const ref = harness.react.useRef({ callback, deps }) as {
            current: { callback: unknown; deps: unknown[] };
        };
        if (deps.some((dep, at) => !Object.is(dep, ref.current.deps[at])))
            ref.current = { callback, deps };
        return ref.current.callback;
    },
}));
const { CommitStarPolling } =
    await import('../../resources/js/components/commit-star-polling');
const { deletedResources } =
    await import('../../resources/js/lib/deleted-resources');
const { connectRealtime } = await import('../../resources/js/lib/realtime');
let report: (
    entries: { target: HTMLElement; isIntersecting: boolean }[],
) => void;
let reads: string[][];
let fake: ReturnType<typeof fakeConnection>;
let context: {
    subscribe: (s: {
        id: string;
        element: HTMLElement;
        prepare: () => null;
    }) => () => void;
};
const element = () =>
    ({
        getBoundingClientRect: () => ({
            width: 10,
            height: 10,
            top: 0,
            bottom: 10,
            left: 0,
            right: 10,
        }),
    }) as HTMLElement;
function render() {
    const tree = harness.render(CommitStarPolling, {
        children: null,
    }) as ReactElement<{ value: typeof context }>;
    context = tree.props.value;
}
beforeEach(() => {
    vi.useFakeTimers();
    reads = [];
    vi.stubGlobal('window', {
        innerWidth: 100,
        innerHeight: 100,
        setInterval,
        clearInterval,
    });
    vi.stubGlobal('document', {
        hidden: false,
        addEventListener() {},
        removeEventListener() {},
    });
    vi.stubGlobal(
        'IntersectionObserver',
        class {
            constructor(callback: typeof report) {
                report = callback;
            }
            observe() {}
            unobserve() {}
            disconnect() {}
        },
    );
    vi.stubGlobal('fetch', async (url: string) => {
        reads.push(
            [...new URL(url, 'http://localhost').searchParams]
                .filter(([key]) => key.startsWith('ids['))
                .map(([, value]) => value),
        );
        return Response.json({ commits: [] });
    });
    fake = fakeConnection();
    connectRealtime(fake.connection);
    render();
    render();
});
afterEach(() => {
    harness.unmount();
    deletedResources.restoreCommit('a');
    deletedResources.restoreCommit('b');
    connectRealtime(null);
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

test('scrolling in one card does not reconcile every already-visible card again', async () => {
    const a = element();
    const b = element();
    context.subscribe({ id: 'a', element: a, prepare: () => null });
    context.subscribe({ id: 'b', element: b, prepare: () => null });
    report([{ target: a, isIntersecting: true }]);
    render();
    joinAll(fake);
    await vi.advanceTimersByTimeAsync(300);
    reads.length = 0;
    report([{ target: b, isIntersecting: true }]);
    render();
    fake.joined.get('commits.b')?.join();
    await vi.advanceTimersByTimeAsync(300);
    assert.deepEqual(reads.flat(), ['b']);
    reads.length = 0;
    report([{ target: b, isIntersecting: true }]);
    render();
    await vi.advanceTimersByTimeAsync(300);
    assert.deepEqual(reads, []);
    fake.joined.get('commits.a')?.emit('engagement');
    await vi.advanceTimersByTimeAsync(300);
    assert.deepEqual(reads.flat(), ['a']);
});

test('returning on screen recovers a deletion whose visibility event was missed', async () => {
    const a = element();
    context.subscribe({ id: 'a', element: a, prepare: () => null });
    report([{ target: a, isIntersecting: true }]);
    render();
    joinAll(fake);
    await vi.advanceTimersByTimeAsync(300);
    deletedResources.restoreCommit('a');
    report([{ target: a, isIntersecting: false }]);
    render();
    fake.joined.get('commits.a')?.emit('visibility', { ids: ['a'] });
    reads.length = 0;
    report([{ target: a, isIntersecting: true }]);
    render();
    await vi.advanceTimersByTimeAsync(300);
    assert.deepEqual(reads.flat(), ['a']);
    assert.equal(deletedResources.hasCommit('a'), true);
});

test('a failed card recovery read does not mark a visible commit unavailable', async () => {
    vi.stubGlobal('fetch', async () => new Response(null, { status: 503 }));
    const a = element();
    context.subscribe({ id: 'a', element: a, prepare: () => null });
    report([{ target: a, isIntersecting: true }]);
    render();
    joinAll(fake);
    await vi.advanceTimersByTimeAsync(300);
    assert.equal(deletedResources.hasCommit('a'), false);
});
