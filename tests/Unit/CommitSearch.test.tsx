/// <reference types="node" />
import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterAll, test, vi } from 'vite-plus/test';
import { SearchResults } from '../../resources/js/components/commit-search-results';
import {
    SearchUnavailable,
    searchCommits,
    TooManySearches,
} from '../../resources/js/lib/commit-search';
import { CommitSearchResults } from '../../resources/js/lib/commit-search-results';

function respond(status: number, body: unknown = {}): void {
    vi.stubGlobal('fetch', () =>
        Promise.resolve(
            new Response(JSON.stringify(body), {
                status,
                headers: { 'Content-Type': 'application/json' },
            }),
        ),
    );
}

afterAll(() => vi.unstubAllGlobals());

test('returns the commits the server found', async () => {
    respond(200, { commits: [{ id: 'found' }] });

    assert.deepEqual(await searchCommits('deploys'), [{ id: 'found' }]);
});

test('tells a search that is down apart from one that found nothing', async () => {
    respond(503, { message: 'Commit search is unavailable right now.' });

    await assert.rejects(
        searchCommits('deploys'),
        (error: unknown) => error instanceof SearchUnavailable,
    );
});

test('tells a throttled search apart from one that is down', async () => {
    respond(429);

    await assert.rejects(
        searchCommits('deploys'),
        (error: unknown) =>
            error instanceof TooManySearches &&
            !(error instanceof SearchUnavailable),
    );
});

test('says the search is down on both tabs, rather than that nothing matched', () => {
    const relevant = new CommitSearchResults('deploys', [], true);

    for (const results of [relevant, relevant.latest()]) {
        const html = renderToStaticMarkup(<SearchResults results={results} />);

        assert.ok(html.includes('error: commit search is down'));
        assert.ok(!html.includes('nothing close to'));
        assert.ok(!html.includes('matches'));
    }
});
