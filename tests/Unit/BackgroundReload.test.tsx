import assert from 'node:assert/strict';
import type { ReloadOptions } from '@inertiajs/core';
import { afterEach, test, vi } from 'vite-plus/test';

const reloads: ReloadOptions[] = [];
vi.mock('@inertiajs/react', () => ({
    router: { reload: (options: ReloadOptions) => reloads.push(options) },
}));

const { reloadInBackground } = await import('../../resources/js/lib/background-poll');
const { changesSince } = await import('../../resources/js/lib/commit-changes');

afterEach(() => {
    reloads.length = 0;
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

test('a background reload settles when its request finishes, not when it is sent', async () => {
    let settled = false;
    const reload = reloadInBackground({ only: ['notifications'] }).then(() => {
        settled = true;
    });
    await Promise.resolve();

    assert.equal(settled, false);
    reloads[0].onFinish?.({} as never);
    await reload;
    assert.equal(settled, true);
});

test('aborting a background reload cancels its request, and it settles once cancelled', async () => {
    const controller = new AbortController();
    let cancelled = false;
    const reload = reloadInBackground({ only: ['notifications'] }, controller.signal);
    reloads[0].onCancelToken?.({ cancel: () => (cancelled = true) });

    controller.abort();
    reloads[0].onFinish?.({} as never);
    await reload;

    assert.equal(cancelled, true);
});

test('commits asking what changed at about the same time ask together, and an unanswered one counts as changed', async () => {
    vi.useFakeTimers();
    const urls: URL[] = [];
    vi.stubGlobal('fetch', async (input: string) => {
        urls.push(new URL(input, 'http://localhost'));

        return urls.length === 1
            ? Response.json({ changes: { a: { changed: true, replies: [], truncated: false }, b: { changed: false, replies: ['r'], truncated: false } } })
            : Response.json({}, { status: 500 });
    });

    const asked = Promise.all([changesSince('a', 2_000), changesSince('b', 1_000)]);
    await vi.advanceTimersByTimeAsync(50);
    const [a, b] = await asked;

    assert.equal(urls.length, 1);
    assert.deepEqual(urls[0].searchParams.getAll('ids[]'), ['a', 'b']);
    assert.equal(urls[0].searchParams.get('since'), '1000');
    assert.equal(a?.changed, true);
    assert.deepEqual(b?.replies, ['r']);

    const failed = changesSince('c', 1_000);
    await vi.advanceTimersByTimeAsync(50);
    assert.equal(await failed, null);
});
