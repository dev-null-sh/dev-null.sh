/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'vite-plus/test';
import {
    emojify,
    emojifyInput,
    emojiQueryBefore,
    suggestEmojis,
} from '../../resources/js/lib/emoji';

const typed = (text: string) => emojifyInput(text, text.length);

test('keeps the shortcode at the caret while it is typed, so ":eye" can still become ":eyes"', () => {
    assert.deepEqual(typed('Shipped it :eye'), {
        text: 'Shipped it :eye',
        caret: 15,
    });
    assert.deepEqual(emojifyInput('a :eye b', 6), {
        text: 'a :eye b',
        caret: 6,
    });
});

test('turns a shortcode into emoji once it is closed or followed by a space', () => {
    assert.deepEqual(typed('Shipped it :eyes:'), {
        text: 'Shipped it 👀',
        caret: 'Shipped it 👀'.length,
    });
    assert.equal(
        typed('Looks good :eye ').text,
        `Looks good ${emojify(':eye')} `,
    );
});

test('turns earlier shortcodes into emoji while a new one is typed', () => {
    assert.equal(typed(':tada: and :ey').text, '🎉 and :ey');
    assert.equal(typed(':heart:eyes').text, '❤️:eyes');
});

test('leaves shortcodes in code alone', () => {
    assert.equal(typed('`:eyes:` and :tada:').text, '`:eyes:` and 🎉');
});

test('finds the emoji query before the caret at the start of a word', () => {
    assert.deepEqual(emojiQueryBefore('Shipped it :ey'), {
        start: 11,
        query: 'ey',
    });
    assert.deepEqual(emojiQueryBefore(':+1'), { start: 0, query: '+1' });
    assert.deepEqual(emojiQueryBefore('`x` :ta'), { start: 4, query: 'ta' });
});

test('stays quiet for one character, emoticons, times, paths, code, and finished shortcodes', () => {
    for (const text of [
        ':e',
        'great :D',
        'at 10:30',
        'std::ve',
        'note:ey',
        '`run :ey',
        ':eyes: ',
    ]) {
        assert.equal(emojiQueryBefore(text), null, text);
    }
});

test('suggests names starting with the query first, then names with a word starting with it', () => {
    const names = suggestEmojis('EYE').map((suggestion) => suggestion.name);

    assert.deepEqual(names.slice(0, 4), [
        'eye',
        'eyes',
        'eyeglasses',
        'eye_speech_bubble',
    ]);
    assert.ok(names.includes('roll_eyes'));
    assert.equal(names.length, 8);
    assert.deepEqual(suggestEmojis('eyes')[0], { name: 'eyes', emoji: '👀' });
    assert.deepEqual(suggestEmojis('zzzz'), []);
});
