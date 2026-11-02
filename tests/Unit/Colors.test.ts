import assert from 'node:assert/strict';
import { test } from 'node:test';
import { colorsIn } from '../../resources/js/lib/colors.ts';

const values = (text: string) => colorsIn(text).map((color) => color.value);

await test('finds hex colors and where they are', () => {
    assert.deepEqual(
        colorsIn(
            'Brand orange is #ff8a4c, white is #fff, and #ff8a4c80 or #fffa',
        ),
        [
            { value: 'ff8a4c', index: 16, length: 7 },
            { value: 'fff', index: 34, length: 4 },
            { value: 'ff8a4c80', index: 44, length: 9 },
            { value: 'fffa', index: 57, length: 5 },
        ],
    );
});

await test('finds a hex color as a whole word, with punctuation after it left out', () => {
    const cases: [string, string[]][] = [
        ['#FFF', ['FFF']],
        ['Is it #fff?!', ['fff']],
        ['(#ff8a4c)', []],
        ['Line\n>#fff', ['fff']],
        ['＃ff8a4c', ['ff8a4c']],
        ['Tab\t#abc', ['abc']],
    ];

    for (const [text, expected] of cases) {
        assert.deepEqual(values(text), expected, text);
    }
});

await test('ignores what only looks like a hex color', () => {
    for (const text of [
        'Issue #123',
        'Gray #333 or #9999',
        '#fffff and #fffffff',
        '#ff8a4g',
        '#fff-x',
        '#ff8a4c.com',
        'a#fff',
        'C#fff',
        '##fff',
        '>>#fff',
    ]) {
        assert.deepEqual(values(text), [], text);
    }
});

await test('finds a hex color after the ">" that starts a whole body', () => {
    assert.deepEqual(colorsIn('>#fff', true), [
        { value: 'fff', index: 1, length: 4 },
    ]);
    assert.deepEqual(colorsIn('>#fff'), []);
    assert.deepEqual(colorsIn('>>#fff', true), []);
});

await test('finds digit-only hex colors that cannot be issue numbers', () => {
    assert.deepEqual(
        values('#000, #0000, #000000, #00000000, #333333 and #99999999'),
        ['000', '0000', '000000', '00000000', '333333', '99999999'],
    );
    assert.deepEqual(values('#00000 and #0000000'), []);
});

await test('ends a hex color at a zero-width character, as a hashtag ends', () => {
    assert.deepEqual(values('#fff\u200Bnext and #abc\u2060'), ['fff', 'abc']);
    assert.deepEqual(values('#fff\u200Cx'), ['fff']);
});
