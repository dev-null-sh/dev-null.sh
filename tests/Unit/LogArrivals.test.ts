import assert from 'node:assert/strict';
import { afterEach, mock, test } from 'node:test';
import { batchLogArrivals } from '../../resources/js/lib/log-arrivals.ts';

afterEach(() => mock.timers.reset());

async function advance(milliseconds: number): Promise<void> {
    mock.timers.tick(milliseconds);
    for (let turn = 0; turn < 10; turn++) await Promise.resolve();
}

void test('busy batches advance through 1, 5, 10, 20 and 30 seconds without extending a window', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const batches: string[][] = [];
    const queue = batchLogArrivals(async (ids) => {
        batches.push(ids);
        return ids.length;
    });

    for (const [index, delay] of [1_000, 5_000, 10_000, 20_000, 30_000, 30_000].entries()) {
        queue.request([`${index}-a`]);
        await advance(delay - 1);
        assert.equal(batches.length, index);
        queue.request([`${index}-b`]);
        await advance(1);
        assert.deepEqual(batches[index], [`${index}-a`, `${index}-b`]);
    }
    queue.cancel();
});

void test('single arrivals keep the current cooldown instead of making the feed slower', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    let reads = 0;
    const queue = batchLogArrivals(async () => { reads++; return 1; });
    queue.request(['first']);
    await advance(1_000);
    queue.request(['second']);
    await advance(5_000);
    queue.request(['third']);
    await advance(5_000);
    assert.equal(reads, 3);
    queue.cancel();
});

void test('an empty cooldown does not fetch and restores the one-second fast path', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    let reads = 0;
    const queue = batchLogArrivals(async () => { reads++; return 2; });
    queue.request(['first']);
    await advance(1_000);
    await advance(5_000);
    await advance(60_000);
    assert.equal(reads, 1);
    queue.request(['after-quiet']);
    await advance(999);
    assert.equal(reads, 1);
    await advance(1);
    assert.equal(reads, 2);
    queue.cancel();
});

void test('irrelevant public signals do not advance the cooldown', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    let reads = 0;
    const queue = batchLogArrivals(async () => { reads++; return 0; });
    queue.request(['not-followed']);
    await advance(1_000);
    queue.request(['reply']);
    await advance(1_000);
    assert.equal(reads, 2);
    queue.cancel();
});

void test('failed reads retain their batch and retry without new socket events', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const batches: string[][] = [];
    const queue = batchLogArrivals(async (ids) => {
        batches.push(ids);
        if (batches.length === 1) throw new Error('network');
        if (batches.length === 2) return null;
        return 1;
    });
    queue.request(['a']);
    await advance(1_000);
    await advance(1_000);
    await advance(1_000);
    assert.deepEqual(batches, [['a'], ['a'], ['a']]);
    await advance(5_000);
    assert.equal(batches.length, 3);
    queue.cancel();
});

void test('reads never overlap and cooldowns start after the preceding read finishes', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    let complete: (added: number) => void = () => {};
    const batches: string[][] = [];
    const queue = batchLogArrivals((ids) => {
        batches.push(ids);
        return new Promise((resolve) => { complete = resolve; });
    });
    queue.request(['a']);
    await advance(1_000);
    queue.request(['b']);
    await advance(30_000);
    assert.deepEqual(batches, [['a']]);
    complete(2);
    await advance(0);
    await advance(4_999);
    assert.equal(batches.length, 1);
    await advance(1);
    assert.deepEqual(batches, [['a'], ['b']]);
    queue.cancel();
    complete(2);
    await advance(60_000);
    assert.equal(batches.length, 2);
});

void test('a full feed response covers queued arrivals heard before the read started', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    let reads = 0;
    const queue = batchLogArrivals(async () => { reads++; return 2; });
    queue.request(['a']);
    await advance(1_000);
    queue.request(['b', 'c']);
    const received = queue.read();
    assert.deepEqual(received(['a', 'b', 'c']), []);
    await advance(5_000);
    assert.equal(reads, 1);
    queue.cancel();
});

void test('signals newer than a feed read are returned for a prompt check, including the same id again', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const queue = batchLogArrivals(async () => 1);
    queue.request(['a']);
    const received = queue.read();
    queue.request(['a', 'b', 'not-in-response']);
    assert.deepEqual(received(['a', 'b']), ['a', 'b']);
    const batches: string[][] = [];
    queue.cancel();
    const other = batchLogArrivals(async (ids) => { batches.push(ids); return 0; });
    other.request(['retained']);
    other.read()(['unrelated']);
    await advance(1_000);
    assert.deepEqual(batches, [['retained']]);
    other.cancel();
});

void test('hidden pages retain their work without reads and resume on the fast path', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const batches: string[][] = [];
    const queue = batchLogArrivals(async (ids) => { batches.push(ids); return 2; });
    queue.request(['a']);
    queue.pause();
    queue.request(['b']);
    await advance(120_000);
    assert.deepEqual(batches, []);
    queue.resume();
    await advance(1_000);
    assert.deepEqual(batches, [['a', 'b']]);
    queue.cancel();
});

void test('each feed owns its cadence and cancellation discards only its own work', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const batches: string[][] = [];
    const first = batchLogArrivals(async () => 2);
    const second = batchLogArrivals(async (ids) => { batches.push(ids); return 0; });
    first.request(['first']);
    first.cancel();
    first.request(['cancelled']);
    first.resume();
    second.request(['second']);
    await advance(1_000);
    assert.deepEqual(batches, [['second']]);
    second.cancel();
});

void test('an authorized catch-up clears only signals that preceded it', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    const batches: string[][] = [];
    const queue = batchLogArrivals(async (ids) => { batches.push(ids); return 0; });
    queue.pause();
    queue.request(['before-catch-up']);
    const caughtUp = queue.read();
    queue.request(['during-catch-up']);
    caughtUp();
    queue.resume();
    await advance(1_000);
    assert.deepEqual(batches, [['during-catch-up']]);
    queue.cancel();
});

void test('a read that finds no eligible arrivals restores fast updates even after busy batches', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    let added = 2;
    let reads = 0;
    const queue = batchLogArrivals(async () => { reads++; return added; });
    queue.request(['a']);
    await advance(1_000);
    queue.request(['b']);
    await advance(5_000);
    added = 0;
    queue.request(['irrelevant']);
    await advance(10_000);
    added = 1;
    queue.request(['relevant-again']);
    await advance(1_000);
    assert.equal(reads, 4);
    queue.cancel();
});
