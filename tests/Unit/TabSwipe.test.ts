import assert from 'node:assert/strict';
import { test } from 'node:test';
import { swipeDirection } from '../../resources/js/lib/tab-swipe.ts';

await test('left and right swipes select the next and previous tabs', () => {
    assert.equal(swipeDirection(-120, 10), 1);
    assert.equal(swipeDirection(120, -10), -1);
});

await test('short and mostly vertical gestures preserve the current tab', () => {
    assert.equal(swipeDirection(-59, 0), null);
    assert.equal(swipeDirection(-120, 90), null);
    assert.equal(swipeDirection(0, 120), null);
    assert.equal(swipeDirection(-60, 30), 1);
});
