import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { fetchRelatedCommits, hiddenAfterFlash } from '../../resources/js/lib/related-commits.ts';
import type { Commit } from '../../resources/js/types';

const realFetch = globalThis.fetch;

afterEach(() => {
    globalThis.fetch = realFetch;
});

function respondWith(response: () => Promise<Response>): void {
    globalThis.fetch = () => response();
}

void test('returns the commits of the response', async () => {
    const commits = [{ id: 'related' }] as Commit[];
    respondWith(() => Promise.resolve(Response.json({ commits })));

    assert.deepEqual(
        await fetchRelatedCommits('/commits/a/related', new AbortController().signal),
        commits,
    );
});

void test('returns an empty list when the server asks to slow down', async () => {
    respondWith(() => Promise.resolve(new Response(null, { status: 429 })));

    assert.deepEqual(
        await fetchRelatedCommits('/commits/a/related', new AbortController().signal),
        [],
    );
});

void test('returns an empty list when the network fails', async () => {
    respondWith(() => Promise.reject(new TypeError('Failed to fetch')));

    assert.deepEqual(
        await fetchRelatedCommits('/commits/a/related', new AbortController().signal),
        [],
    );
});

void test('rejects when the request was aborted, so a stale response never lands', async () => {
    const controller = new AbortController();
    controller.abort();
    respondWith(() => Promise.reject(new DOMException('Aborted', 'AbortError')));

    await assert.rejects(
        fetchRelatedCommits('/commits/a/related', controller.signal),
        { name: 'AbortError' },
    );
});

void test('hides a deleted commit and shows it again once restored', () => {
    const hidden = hiddenAfterFlash(new Set(), { deleted: { id: 'related' } });

    assert.deepEqual([...hidden], ['related']);
    assert.deepEqual([...hiddenAfterFlash(hidden, { restored: { id: 'related' } })], []);
});

void test('leaves the hidden commits alone on other flashes', () => {
    const hidden = new Set(['related']);

    assert.equal(hiddenAfterFlash(hidden, {}), hidden);
    assert.deepEqual([...hiddenAfterFlash(hidden, { restored: { id: 'other' } })], ['related']);
});
