import assert from 'node:assert/strict';
import { test } from 'node:test';
import { withPinChanges } from '../../resources/js/lib/pinned-commit.ts';
import type { Commit, CursorPaginated } from '../../resources/js/types/index.ts';

const commit = (id: string, minute: number) => ({ id, created_at: `2026-11-03T10:${String(minute).padStart(2, '0')}:00Z` }) as Commit;
const page = (commits: Commit[], nextCursor: string | null = null) => ({ data: commits, next_cursor: nextCursor }) as CursorPaginated<Commit>;
const ids = (commits: CursorPaginated<Commit>) => commits.data.map(({ id }) => id);

void test('a commit unpinned elsewhere goes back among the loaded ones, newest first', () => {
    const list = page([commit('c', 30), commit('a', 10)]);

    assert.deepEqual(ids(withPinChanges(list, null, [commit('b', 20)])), ['c', 'b', 'a']);
});

void test('a commit pinned elsewhere leaves the list, and one replaced by another pin comes back', () => {
    const list = page([commit('c', 30), commit('b', 20), commit('a', 10)]);

    assert.deepEqual(ids(withPinChanges(list, commit('b', 20), [])), ['c', 'a']);
    assert.deepEqual(ids(withPinChanges(page([commit('c', 30), commit('a', 10)]), commit('c', 30), [commit('b', 20)])), ['b', 'a']);
});

void test('an unpinned commit older than everything loaded waits for its page, unless every page is loaded', () => {
    assert.deepEqual(ids(withPinChanges(page([commit('c', 30)], 'next'), null, [commit('a', 10)])), ['c']);
    assert.deepEqual(ids(withPinChanges(page([commit('c', 30)]), null, [commit('a', 10)])), ['c', 'a']);
});

void test('a commit already listed is never shown twice', () => {
    assert.deepEqual(ids(withPinChanges(page([commit('b', 20)]), null, [commit('b', 20)])), ['b']);
});

void test('a deleted or hidden former pin never returns as an ordinary commit, but undo can restore it', () => {
    const list = page([commit('c', 30), commit('a', 10)]);
    const oldPin = commit('b', 20);
    const hidden = new Set(['b']);

    assert.deepEqual(ids(withPinChanges(list, null, [oldPin], (id) => hidden.has(id))), ['c', 'a']);
    hidden.delete('b');
    assert.deepEqual(ids(withPinChanges(list, null, [oldPin], (id) => hidden.has(id))), ['c', 'b', 'a']);
});
