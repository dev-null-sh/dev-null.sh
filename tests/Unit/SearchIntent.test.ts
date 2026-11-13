import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    awaitsDeveloperName,
    offersCommitSearch,
    searchesCommits,
    searchIntent,
} from '../../resources/js/lib/search-intent.ts';

await test('a lone @ waits for a developer name instead of searching', () => {
    assert.equal(awaitsDeveloperName('@'), true);
    assert.equal(awaitsDeveloperName('  @ '), true);
    assert.equal(searchesCommits(searchIntent('@')), false);
});

await test('a handle or an empty query does not wait for a developer name', () => {
    assert.equal(awaitsDeveloperName('@nuno'), false);
    assert.equal(awaitsDeveloperName(''), false);
    assert.equal(awaitsDeveloperName('caching'), false);
});

await test('a word searched as a developer still offers the full commit search', () => {
    for (const term of ['php', 'rust', 'Laravel', 'Swift', 'nuno-dev']) {
        assert.equal(searchesCommits(searchIntent(term)), false, term);
        assert.equal(offersCommitSearch(term), true, term);
    }
});

await test('an explicit @ or a term too short to search offers no commit search', () => {
    assert.equal(offersCommitSearch('@laravel'), false);
    assert.equal(offersCommitSearch(' @nuno '), false);
    assert.equal(offersCommitSearch('go'), false);
    assert.equal(offersCommitSearch(''), false);
});
