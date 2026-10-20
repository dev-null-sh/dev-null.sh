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

await test('a plain lowercase word of four or more letters also searches commits', () => {
    for (const term of ['rust', 'claw', 'redis', 'react', 'caching']) {
        assert.equal(searchIntent(term), 'either', term);
        assert.equal(searchesCommits(searchIntent(term)), true, term);
    }
});

await test('handle-shaped and very short words search developers only', () => {
    for (const term of ['@claw', 'go', 'ai', 'vac', 'nuno2', 'dev-null', 'Claw']) {
        assert.equal(searchIntent(term), 'developer', term);
        assert.equal(searchesCommits(searchIntent(term)), false, term);
    }
});

await test('a word with a space is a topic', () => {
    assert.equal(searchIntent('claw bot'), 'topic');
    assert.equal(searchesCommits(searchIntent('claw bot')), true);
});
