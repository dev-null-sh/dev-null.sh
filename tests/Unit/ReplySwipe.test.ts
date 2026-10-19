import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    isReplySwipe,
    replyPull,
    replyThreshold,
} from '../../resources/js/lib/reply-swipe.ts';

await test("pulling someone else's message right replies", () => {
    assert.equal(replyPull(60, false), 60);
    assert.equal(replyPull(-60, false), 0);
});

await test('pulling my own message left replies', () => {
    assert.equal(replyPull(-60, true), 60);
    assert.equal(replyPull(60, true), 0);
});

await test('the pull stops a little past the reply threshold', () => {
    assert.ok(replyPull(400, false) > replyThreshold);
    assert.ok(replyPull(400, false) < 100);
});

await test('a first move more sideways than up or down is a reply swipe', () => {
    assert.equal(isReplySwipe(6, 3), true);
    assert.equal(isReplySwipe(-6, 4), true);
    assert.equal(isReplySwipe(3, 6), false);
    assert.equal(isReplySwipe(0, 0), false);
});
