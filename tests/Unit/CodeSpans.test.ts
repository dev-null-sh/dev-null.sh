import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    bodySegments,
    mapProse,
    mapTypedProse,
    splitCode,
} from '../../resources/js/lib/code.ts';

const nested = '````markdown\n```\nfence inside fence\n```\n````';

test('keeps a shorter fence inside a code block', () => {
    assert.deepEqual(bodySegments(`before\n${nested}\nafter`), [
        { type: 'text', text: 'before' },
        {
            type: 'block',
            language: 'markdown',
            filename: null,
            html: '```\nfence inside fence\n```',
        },
        { type: 'text', text: 'after' },
    ]);
});

test('closes a code block with as many backticks or more', () => {
    assert.deepEqual(splitCode('````\ncode\n`````\nprose `x`'), [
        '',
        '````\ncode\n`````',
        '\nprose ',
        '`x`',
        '',
    ]);
});

test('leaves the code of a nested block alone, committed or being typed', () => {
    const upper = (prose: string) => prose.toUpperCase();

    assert.equal(mapProse(`a\n${nested}\nb`, upper), `A\n${nested}\nB`);
    assert.equal(
        mapTypedProse('a\n````\n```\nstill typing', upper),
        'A\n````\n```\nstill typing',
    );
});
