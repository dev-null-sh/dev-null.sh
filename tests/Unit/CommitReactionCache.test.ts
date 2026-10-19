import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createReactionCache } from '../../resources/js/lib/commit-reaction-cache.ts';
import type { ReactionState } from '../../resources/js/lib/commit-reaction-cache.ts';

const old: ReactionState = { reactions: [{ emoji: '🔥', public: false, repositories: ['private-repo'] }], repositories: [{ id: 'private-repo', repository: 'you/archive', private: true }] };
const memory = () => {
    const values = new Map<string, string>();
    return { read: (key: string) => values.get(key) ?? '', write: (key: string, value: string) => { values.set(key, value); } };
};
void test('cached private state is available synchronously after reload and isolated by viewer', async () => {
    const storage = memory();
    const cache = createReactionCache(storage, async () => old);
    await cache.refresh('me', 'post');
    const reload = createReactionCache(storage, () => new Promise(() => {}));
    void reload.refresh('me', 'post');
    assert.deepEqual(reload.read('me', 'post'), old);
    assert.equal(reload.read('someone-else', 'post'), undefined);
});
void test('failed background refresh retains cached reactions and destination labels', async () => {
    const storage = memory();
    await createReactionCache(storage, async () => old).refresh('me', 'post');
    const cache = createReactionCache(storage, async () => { throw Error('offline'); });
    await cache.refresh('me', 'post');
    assert.deepEqual(cache.read('me', 'post'), old);
});
void test('a late background response cannot resurrect a removed reaction', async () => {
    let resolve!: (state: ReactionState) => void;
    const cache = createReactionCache(memory(), () => new Promise((done) => { resolve = done; }));
    const refresh = cache.refresh('me', 'post');
    const finish = cache.begin('me', 'post', old);
    finish({ emoji: '🔥' });
    resolve(old);
    await refresh;
    assert.deepEqual(cache.read('me', 'post')?.reactions, []);
});
void test('saving one emoji retains the others and failed writes preserve cached state', async () => {
    const cache = createReactionCache(memory(), async () => old);
    await cache.refresh('me', 'post');
    cache.begin('me', 'post', old)({ emoji: '🚀', destinations: { public: true, repositories: [] } });
    cache.begin('me', 'post', old)();
    assert.deepEqual(cache.read('me', 'post')?.reactions.map((r) => r.emoji), ['🚀', '🔥']);
});
void test('duplicate controls share one background request while cached reads stay available', async () => {
    let calls = 0;
    let resolve!: (state: ReactionState) => void;
    const cache = createReactionCache(memory(), () => { calls++; return new Promise((done) => { resolve = done; }); });
    const first = cache.refresh('me', 'post');
    const second = cache.refresh('me', 'post');
    assert.equal(calls, 1);
    assert.equal(cache.read('me', 'post'), undefined);
    resolve(old);
    await Promise.all([first, second]);
    await cache.refresh('me', 'post');
    assert.equal(calls, 1);
});
