import assert from 'node:assert/strict';
import { test } from 'node:test';
import { swipedLane } from '../../resources/js/lib/lane-swipe.ts';

await test('swiping left moves to the next lane', () => {
    assert.equal(swipedLane('following', -120, 10), 'latest');
    assert.equal(swipedLane('latest', -120, 10), 'trending');
});

await test('swiping right moves to the previous lane', () => {
    assert.equal(swipedLane('latest', 120, -10), 'following');
    assert.equal(swipedLane('trending', 120, 10), 'latest');
});

await test('swiping past the first or last lane stays put', () => {
    assert.equal(swipedLane('following', 120, 0), null);
    assert.equal(swipedLane('trending', -120, 0), null);
});

await test('short or mostly vertical swipes do not change lanes', () => {
    assert.equal(swipedLane('following', -30, 0), null);
    assert.equal(swipedLane('following', -120, 90), null);
});
