/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'vite-plus/test';
import {
    emojiName,
    searchEmoji,
    searchEmojiByUse,
} from '../../resources/js/lib/emoji';

test('suggests the shortcodes starting with the query first, shortest first', () => {
    const names = searchEmoji('smi').map((emoji) => emoji.name);

    assert.deepEqual(names.slice(0, 3), ['smile', 'smirk', 'smiley']);
    assert.ok(names.every((name) => name.startsWith('smi')));
});

test('falls back to the shortcodes with a word starting with the query, ignoring case', () => {
    const names = searchEmoji('ART').map((emoji) => emoji.name);

    assert.deepEqual(names.slice(0, 2), ['art', 'artist']);
    assert.ok(names.includes('man_artist'));
    assert.ok(names.includes('performing_arts'));
});

test('lists the shortcodes containing the query inside a word last', () => {
    const names = searchEmoji('apple').map((emoji) => emoji.name);

    assert.deepEqual(names, ['apple', 'green_apple', 'pineapple']);
});

test('limits the suggestions and pairs each shortcode with its emoji', () => {
    const suggestions = searchEmoji('a', 5);

    assert.equal(suggestions.length, 5);
    assert.deepEqual(searchEmoji('+1', 1), [{ name: '+1', emoji: '👍' }]);
    assert.deepEqual(searchEmoji('no_such_shortcode'), []);
});

test('searches each emoji once, putting the ones the user reacts with most first within a match', () => {
    const results = searchEmojiByUse('heart', new Map([['😍', 3]]));

    assert.deepEqual(
        results.slice(0, 2).map((result) => result.emoji),
        ['😍', '❤️'],
    );
    assert.equal(
        searchEmojiByUse('thumbs', new Map()).filter(
            (result) => result.emoji === '👍',
        ).length,
        1,
    );
    assert.equal(searchEmojiByUse(':rock', new Map())[0].emoji, '🪨');
    assert.equal(emojiName('👍'), '+1');
});
