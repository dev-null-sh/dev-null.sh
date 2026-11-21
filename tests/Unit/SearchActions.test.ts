import assert from 'node:assert/strict';
import { test } from 'node:test';
import { searchActions } from '../../resources/js/lib/search-actions.ts';

const offline = { isLive: false };

await test('offers an action once the search starts one of its words', () => {
    assert.deepEqual(searchActions('commit', offline), ['commit']);
    assert.deepEqual(searchActions('Comm', offline), ['commit']);
    assert.deepEqual(searchActions('write', offline), ['commit']);
    assert.deepEqual(searchActions('feat', offline), ['feature']);
    assert.deepEqual(searchActions('bug', offline), ['bug']);
    assert.deepEqual(searchActions('report', offline), ['bug']);
    assert.deepEqual(searchActions('go  live ', offline), ['go-live']);
    assert.deepEqual(searchActions('stream', offline), ['go-live']);
});

await test('offers nothing for short searches or ones that go past an action', () => {
    assert.deepEqual(searchActions('', offline), []);
    assert.deepEqual(searchActions('co', offline), []);
    assert.deepEqual(searchActions('commits about caching', offline), []);
    assert.deepEqual(searchActions('bugs', offline), []);
    assert.deepEqual(searchActions('@bug', offline), []);
});

await test('takes the composer slash commands from the slash on', () => {
    assert.deepEqual(searchActions('/', offline), ['feature', 'bug']);
    assert.deepEqual(searchActions('/b', offline), ['bug']);
    assert.deepEqual(searchActions('/feature', offline), ['feature']);
    assert.deepEqual(searchActions('/commit', offline), []);
});

await test('ends a stream instead of starting one, only when asked by name', () => {
    const live = { isLive: true };

    assert.deepEqual(searchActions('go live', live), []);
    assert.deepEqual(searchActions('live', live), []);
    assert.deepEqual(searchActions('end stream', live), ['end-stream']);
    assert.deepEqual(searchActions('stop', live), ['end-stream']);
    assert.deepEqual(searchActions('end stream', offline), []);
});
