import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createLogPreference } from '../../resources/js/lib/log-preference.ts';
import type { Log } from '../../resources/js/types/index.ts';

const tick = () => new Promise<void>((resolve) => setImmediate(resolve));

void test('rapid tab visits serialize saves and keep only the latest queued log', async () => {
    const writes: Log[] = [];
    const complete: (() => void)[] = [];
    const remember = createLogPreference(
        'following',
        (log) => {
            writes.push(log);
            return new Promise<void>((resolve) => complete.push(resolve));
        },
        () => assert.fail('unexpected save failure'),
    );

    remember('latest');
    remember('trending');
    remember('following');
    assert.deepEqual(writes, ['latest']);
    complete.shift()!();
    await tick();
    assert.deepEqual(writes, ['latest', 'following']);
    complete.shift()!();
    await tick();
    remember('following');
    assert.deepEqual(writes, ['latest', 'following']);
});

void test('a failed save reports once and a different log can still be saved', async () => {
    let attempts = 0;
    let failures = 0;
    const remember = createLogPreference(
        'following',
        async () => {
            if (++attempts === 1) throw new Error('offline');
        },
        () => failures++,
    );

    remember('latest');
    await tick();
    assert.equal(failures, 1);
    assert.equal(attempts, 1);
    remember('trending');
    await tick();
    assert.equal(attempts, 2);
    remember('trending');
    assert.equal(attempts, 2);
});

void test('a failed older save does not discard the newest tab', async () => {
    const writes: Log[] = [];
    let fail: (reason: Error) => void = () => {};
    const remember = createLogPreference(
        'following',
        (log) => {
            writes.push(log);
            return writes.length === 1
                ? new Promise<void>((_, reject) => {
                      fail = reject;
                  })
                : Promise.resolve();
        },
        () => assert.fail('superseded failures must not show a toast'),
    );

    remember('latest');
    remember('trending');
    fail(new Error('offline'));
    await tick();
    assert.deepEqual(writes, ['latest', 'trending']);
});
