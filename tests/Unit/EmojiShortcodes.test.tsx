/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'vite-plus/test';
import { emojify, emojifyInput } from '../../resources/js/lib/emoji';

test('replaces closed shortcodes and consumes their closing colon', () => {
    assert.equal(
        emojify('shipped :rocket:, :heart::like: and :fire:s'),
        'shipped 🚀, ❤️👍 and 🔥s',
    );
});

test('leaves shortcodes without their closing colon untouched', () => {
    assert.equal(
        emojify('plan :a, then :smile and :heart'),
        'plan :a, then :smile and :heart',
    );
});

test('keeps the shortcode being typed until its closing colon', () => {
    assert.deepEqual(emojifyInput(':a', 2), { text: ':a', caret: 2 });
    assert.deepEqual(emojifyInput(':smile', 6), { text: ':smile', caret: 6 });
    assert.deepEqual(emojifyInput(':smile ', 7), { text: ':smile ', caret: 7 });
    assert.deepEqual(emojifyInput('so :smile: ok', 10), {
        text: 'so 😄 ok',
        caret: 5,
    });
});
