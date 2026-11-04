import assert from 'node:assert/strict';
import { test } from 'node:test';
import { reconcileReplies } from '../../resources/js/lib/thread-replies.ts';
import type { ThreadCommit } from '../../resources/js/types/index.ts';

const reply = (id: string, minute: number, body = id): ThreadCommit =>
    ({ id, parent_id: 'root', created_at: `2026-11-03T10:${String(minute).padStart(2, '0')}:00.000000Z`, body }) as ThreadCommit;
const ids = (replies: ThreadCommit[]) => replies.map(({ id }) => id);

void test('adds a new reply after the last one once the reader has loaded them all', () => {
    const loaded = [reply('a', 1), reply('b', 2)];

    assert.deepEqual(ids(reconcileReplies(loaded, false, ['c'], [reply('c', 3)], false)), ['a', 'b', 'c']);
});

void test('leaves a newer reply for the next page while there are more to load, but places an approved older one', () => {
    const loaded = [reply('a', 1), reply('c', 5)];

    assert.deepEqual(ids(reconcileReplies(loaded, true, ['z'], [reply('z', 9)], false)), ['a', 'c']);
    assert.deepEqual(ids(reconcileReplies(loaded, true, ['b'], [reply('b', 3)], false)), ['a', 'b', 'c']);
});

void test('replaces a changed reply where it is and drops one the viewer may no longer see', () => {
    const loaded = [reply('a', 1), reply('b', 2), reply('c', 3)];
    const tombstone = { id: 'b', parent_id: 'root', deleted_at: '2026-11-03T11:00:00Z' } as ThreadCommit;

    assert.deepEqual(reconcileReplies(loaded, false, ['b'], [tombstone], false)[1], tombstone);
    assert.deepEqual(ids(reconcileReplies(loaded, false, ['c'], [], false)), ['a', 'b']);
});

void test('keeps the newest first in a preview, and never adds a reply twice', () => {
    const loaded = [reply('c', 3), reply('a', 1)];

    assert.deepEqual(ids(reconcileReplies(loaded, false, ['b', 'c'], [reply('b', 2), reply('c', 3, 'edited')], true)), ['c', 'b', 'a']);
});

void test('leaves the loaded replies alone when the change is about others', () => {
    const loaded = [reply('a', 1)];

    assert.equal(reconcileReplies(loaded, false, ['x'], [], false).length, 1);
});
