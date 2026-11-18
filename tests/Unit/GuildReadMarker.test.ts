import assert from 'node:assert/strict';
import { test } from 'node:test';
import { firstReadCommit } from '../../resources/js/lib/guild-read-marker.ts';
import type { Commit } from '../../resources/js/types/index.ts';

const commit = (id: string, createdAt: string, handle: string): Commit =>
    ({ id, created_at: createdAt, author: { handle } }) as Commit;

const missed = commit('missed', '2026-11-18T11:00:00+00:00', 'author');
const own = commit('own', '2026-11-18T11:00:00+00:00', 'reader');
const read = commit('read', '2026-11-18T09:00:00+00:00', 'author');
const readFrom = '2026-11-18T10:00:00+00:00';

await test('marks the first commit the reader already saw', () => {
    assert.equal(firstReadCommit([missed, read], readFrom, 'reader'), read);
});

await test('marks nothing when the reader wrote every new commit', () => {
    assert.equal(firstReadCommit([own, read], readFrom, 'reader'), undefined);
});

await test('marks nothing when every commit is new', () => {
    assert.equal(firstReadCommit([missed], readFrom, 'reader'), undefined);
});

await test('marks nothing when no commit is new', () => {
    assert.equal(firstReadCommit([read], readFrom, 'reader'), undefined);
});

await test('marks nothing in a guild the reader did not join', () => {
    assert.equal(firstReadCommit([missed, read], null, 'reader'), undefined);
});
