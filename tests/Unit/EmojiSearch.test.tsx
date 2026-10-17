/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'vite-plus/test';
import { searchEmoji } from '../../resources/js/lib/emoji';

test('suggests the shortcodes starting with the query first, shortest first', () => {
    const names = searchEmoji('smi').map((emoji) => emoji.name);

    assert.deepEqual(names.slice(0, 3), ['smile', 'smirk', 'smiley']);
    assert.ok(names.every((name) => name.startsWith('smi')));
});

test('falls back to the shortcodes containing the query, ignoring case', () => {
    const names = searchEmoji('CAT').map((emoji) => emoji.name);

    assert.equal(names[0], 'cat');
    assert.ok(names.slice(1).every((name) => name.includes('cat')));
});

test('limits the suggestions and pairs each shortcode with its emoji', () => {
    const suggestions = searchEmoji('a', 5);

    assert.equal(suggestions.length, 5);
    assert.deepEqual(searchEmoji('+1', 1), [{ name: '+1', emoji: '👍' }]);
    assert.deepEqual(searchEmoji('no_such_shortcode'), []);
});
