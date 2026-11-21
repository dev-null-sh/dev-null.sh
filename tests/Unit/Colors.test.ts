import assert from 'node:assert/strict';
import { test } from 'node:test';
import { colorsIn } from '../../resources/js/lib/colors.ts';

const values = (text: string) => colorsIn(text).map((color) => color.value);

await test('finds hex colors and where they are', () => {
    assert.deepEqual(colorsIn('color(#Fff), color(#dead) #0ff'), [
        { value: 'Fff', label: '#Fff', index: 0, length: 11 },
        { value: 'dead', label: '#dead', index: 13, length: 12 },
        { value: '0ff', label: '#0ff', index: 26, length: 4 },
    ]);
});

await test('finds a hex color as a whole word, with punctuation after it left out', () => {
    const cases: [string, string[]][] = [
        ['COLOR(#123)', ['123']],
        ['Try (color(#ff8a4c))!', ['ff8a4c']],
        ['Try color(#ff8a4c80)', ['ff8a4c80']],
        ['Is it #0ff?!', ['0ff']],
        ['(#0f0)', []],
        ['Line\n>#0ff', ['0ff']],
        ['＃ff8a4c', ['ff8a4c']],
        ['＃fff', ['fff']],
        ['Tab\t#0bc', ['0bc']],
    ];

    for (const [text, expected] of cases) {
        assert.deepEqual(values(text), expected, text);
    }
});

await test('ignores what only looks like a hex color', () => {
    for (const text of [
        'Issue #123',
        'Gray #333 or #9999',
        '#fff #dead #ff8a4c #deadbeef',
        'color(#fffff) color(#fffffff) color(#deadg)',
        'discolor(#dead) #color(#dead)',
        '#0f8a4g',
        '#0ff-x',
        '#0f8a4c.com',
        'a#0ff',
        'C#0ff',
        '##0ff',
        '>>#0ff',
    ]) {
        assert.deepEqual(values(text), [], text);
    }
});

await test('finds a hex color after the ">" that starts a whole body', () => {
    assert.deepEqual(colorsIn('>#0ff', true), [
        { value: '0ff', label: '#0ff', index: 1, length: 4 },
    ]);
    assert.deepEqual(colorsIn('>#0ff'), []);
    assert.deepEqual(colorsIn('>>#0ff', true), []);
});

await test('finds digit-only hex colors that cannot be issue numbers', () => {
    assert.deepEqual(
        values('#000, #0000, #000000, #00000000, #333333 and #99999999'),
        ['000', '0000', '000000', '00000000', '333333', '99999999'],
    );
    assert.deepEqual(values('#00000 and #0000000'), []);
});

await test('ends a hex color at a zero-width character, as a hashtag ends', () => {
    assert.deepEqual(values('#0ff\u200Bnext and #0bc\u2060'), ['0ff', '0bc']);
    assert.deepEqual(values('#0ff\u200Cx'), ['0ff']);
});
