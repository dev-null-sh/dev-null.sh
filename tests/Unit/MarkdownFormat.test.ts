import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatMarkdown } from '../../resources/js/lib/markdown-format.ts';

const apply = (value: string, start: number, end: number, format: Parameters<typeof formatMarkdown>[3]) => {
    const edit = formatMarkdown(value, start, end, format);
    const next = value.slice(0, edit.from) + edit.insert + value.slice(edit.to);

    return [next, next.slice(edit.selectionStart, edit.selectionEnd)];
};

await test('wraps the selection inline and keeps it selected', () => {
    assert.deepEqual(apply('ship it now', 5, 7, 'bold'), ['ship **it** now', 'it']);
    assert.deepEqual(apply('ship it now', 5, 7, 'italic'), ['ship _it_ now', 'it']);
    assert.deepEqual(apply('run make', 4, 8, 'code'), ['run `make`', 'make']);
    assert.deepEqual(apply('', 0, 0, 'bold'), ['****', '']);
});

await test('turns the selection into a link with its URL selected', () => {
    assert.deepEqual(apply('read the changelog', 9, 18, 'link'), [
        'read the [changelog](https://)',
        'https://',
    ]);
});

await test('prefixes every line the selection touches', () => {
    assert.deepEqual(apply('intro\nfirst\nsecond\noutro', 8, 14, 'bulleted'), [
        'intro\n- first\n- second\noutro',
        '',
    ]);
    assert.deepEqual(apply('first\nsecond', 0, 12, 'numbered'), [
        '1. first\n2. second',
        '',
    ]);
    assert.deepEqual(apply('What shipped', 3, 3, 'heading'), ['## What shipped', '']);
    assert.deepEqual(apply('a\nquote', 2, 2, 'quote'), ['a\n> quote', '']);
});
