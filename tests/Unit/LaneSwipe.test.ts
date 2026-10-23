import assert from 'node:assert/strict';
import { test } from 'node:test';
import { swipedLane } from '../../resources/js/lib/lane-swipe.ts';

const logLanes = ['following', 'latest', 'trending'] as const;
const inboxLanes = ['open', 'fetched', 'stashed'] as const;
const inboxWithoutFetched = ['open', 'stashed'] as const;

await test('swiping left moves to the next log lane', () => {
    assert.equal(swipedLane('following', logLanes, -120, 10), 'latest');
    assert.equal(swipedLane('latest', logLanes, -120, 10), 'trending');
});

await test('swiping right moves to the previous log lane', () => {
    assert.equal(swipedLane('latest', logLanes, 120, -10), 'following');
    assert.equal(swipedLane('trending', logLanes, 120, 10), 'latest');
});

await test('swiping left advances one visible inbox tab at a time', () => {
    assert.equal(swipedLane('open', inboxLanes, -120, 0), 'fetched');
    assert.equal(swipedLane('fetched', inboxLanes, -120, 0), 'stashed');
});

await test('swiping right moves back one visible inbox tab at a time', () => {
    assert.equal(swipedLane('stashed', inboxLanes, 120, 0), 'fetched');
    assert.equal(swipedLane('fetched', inboxLanes, 120, 0), 'open');
});

await test('the inbox skips hidden fetched tabs without wrapping', () => {
    assert.equal(swipedLane('open', inboxWithoutFetched, -120, 0), 'stashed');
    assert.equal(swipedLane('stashed', inboxWithoutFetched, 120, 0), 'open');
    assert.equal(swipedLane('open', inboxWithoutFetched, 120, 0), null);
    assert.equal(swipedLane('stashed', inboxWithoutFetched, -120, 0), null);
});

await test('swiping past either end of the log lanes stays put', () => {
    assert.equal(swipedLane('following', logLanes, 120, 0), null);
    assert.equal(swipedLane('trending', logLanes, -120, 0), null);
});

await test('short or mostly vertical swipes do not change lanes', () => {
    assert.equal(swipedLane('following', logLanes, -30, 0), null);
    assert.equal(swipedLane('following', logLanes, -120, 90), null);
});

await test('empty sequences and a lane missing from the sequence stay put', () => {
    assert.equal(swipedLane('open', [], -120, 0), null);
    assert.equal(swipedLane('fetched', inboxWithoutFetched, -120, 0), null);
});
