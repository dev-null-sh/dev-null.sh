/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { updateQuotedCommits } from '../../resources/js/lib/commit-mutation-options';
import type { Commit, DeletedCommit } from '../../resources/js/types';

const target = {
    id: 'target',
    body: 'Original body',
    parent_id: 'parent',
    deleted_at: null,
} as Commit;
const quotes = [
    { id: 'before', quoted_commit_id: null },
    { id: 'quote', quoted_commit_id: target.id, quoted_commit: target },
    { id: 'after', quoted_commit_id: 'unrelated', quoted_commit: null },
] as Commit[];
const tombstone: DeletedCommit = {
    id: target.id,
    parent_id: target.parent_id,
    deleted_at: '2026-10-16T12:00:00Z',
};

void test('replaces every loaded quote embed with a tombstone without changing row order or unrelated rows', () => {
    const rows = [...quotes, { ...quotes[1], id: 'second-quote' }];
    const updated = updateQuotedCommits(rows, tombstone);
    assert.deepEqual(
        updated.map((row) => row.id),
        rows.map((row) => row.id),
    );
    assert.strictEqual(updated[0], rows[0]);
    assert.strictEqual(updated[2], rows[2]);
    assert.deepEqual(updated[1].quoted_commit, tombstone);
    assert.deepEqual(updated[3].quoted_commit, tombstone);
    assert.ok(!('body' in updated[1].quoted_commit!));
    assert.strictEqual(rows[1].quoted_commit, target);
});

void test('restores embedded reply content even when the target is absent from the loaded feed', () => {
    const deleted = updateQuotedCommits(quotes, tombstone);
    const restored = { ...target, body: 'Restored body' };
    const updated = updateQuotedCommits(deleted, restored);
    assert.strictEqual(updated[1].quoted_commit, restored);
    assert.equal(updated.length, quotes.length);
    assert.strictEqual(updated[0], quotes[0]);
    assert.strictEqual(updated[2], quotes[2]);
});

void test('keeps the same rows when no loaded commit quotes the changed target', () => {
    const updated = updateQuotedCommits(quotes, { ...target, id: 'other' });
    assert.deepEqual(updated, quotes);
    updated.forEach((row, index) => assert.strictEqual(row, quotes[index]));
});
