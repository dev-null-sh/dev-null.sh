import assert from 'node:assert/strict';
import { test } from 'node:test';
import { isKeyboardOpen } from '../../resources/js/lib/visual-viewport.ts';

await test('the keyboard opens for a focused field that loses room', () => {
    assert.equal(isKeyboardOpen(false, true, 300), true);
    assert.equal(isKeyboardOpen(false, true, 40), false);
});

await test('losing room without a focused field is not the keyboard', () => {
    assert.equal(isKeyboardOpen(false, false, 300), false);
});

await test('an open keyboard stays open until the room comes back', () => {
    assert.equal(isKeyboardOpen(true, false, 300), true);
    assert.equal(isKeyboardOpen(true, false, 40), false);
});
