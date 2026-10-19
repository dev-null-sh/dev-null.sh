import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    awaitsDeveloperName,
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
