import assert from 'node:assert/strict';
import { test } from 'node:test';
import { CommitSearchResults } from '../../resources/js/lib/commit-search-results.ts';
import type { Commit } from '../../resources/js/types';

await test('latest keeps the past week newest first without changing relevance order', (context) => {
    context.mock.method(Date, 'now', () => Date.parse('2026-11-03T12:00:00Z'));
    const commits = [
        { id: 'old', created_at: '2026-10-27T11:59:59.999Z' },
        { id: 'boundary', created_at: '2026-10-27T12:00:00Z' },
        { id: 'recent', created_at: '2026-11-02T13:00:00+01:00' },
        { id: 'newest', created_at: '2026-11-03T11:00:00Z' },
        { id: 'middle', created_at: '2026-10-30T12:00:00Z' },
    ] as Commit[];
    const relevant = new CommitSearchResults('testing strategies', commits);

    const latest = relevant.latest();

    assert.equal(latest.term, 'testing strategies');
    assert.deepEqual(
        latest.commits.map((commit) => commit.id),
        ['newest', 'recent', 'middle', 'boundary'],
    );
    assert.deepEqual(
        relevant.commits.map((commit) => commit.id),
        ['old', 'boundary', 'recent', 'newest', 'middle'],
    );
});

await test('latest is empty when there are no recent matches', (context) => {
    context.mock.method(Date, 'now', () => Date.parse('2026-11-03T12:00:00Z'));

    for (const commits of [
        [],
        [{ id: 'old', created_at: '2026-10-20T12:00:00Z' }] as Commit[],
    ]) {
        assert.deepEqual(
            new CommitSearchResults('an idea', commits).latest().commits,
            [],
        );
    }
});
