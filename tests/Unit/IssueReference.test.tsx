/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'vite-plus/test';
import { issueReferenceBeforeCaret } from '../../resources/js/lib/issue-reference';

test('suggests only the exact number immediately before the caret', () => {
    assert.deepEqual(issueReferenceBeforeCaret('See #72', 7), {
        start: 4,
        query: '72',
    });
    assert.deepEqual(issueReferenceBeforeCaret('See (#722).', 9), {
        start: 5,
        query: '722',
    });
});

test('stops suggesting after a delimiter or continued prose', () => {
    for (const ending of [
        ' ',
        '.',
        ',',
        '!',
        '?',
        ';',
        ':',
        ')',
        ']',
        '}',
        '\n',
        ' for details',
    ]) {
        const text = `See #722${ending}`;
        assert.equal(issueReferenceBeforeCaret(text, text.length), null, text);
    }
});

test('does not link code, colors, URL fragments, other repositories, or partial numbers', () => {
    for (const text of [
        '`#123',
        '`#123`',
        '```\n#123',
        '```\n#123\n```',
        '#123abc',
        '#123_foo',
        'owner/repo#123',
        'https://example.com/#123',
        '##123',
        '#0123',
        '#0',
        '#12345678901',
    ]) {
        assert.equal(issueReferenceBeforeCaret(text, text.length), null, text);
    }
    assert.equal(issueReferenceBeforeCaret('#1234', 3), null);
});

test('preserves offsets after code and non-ASCII text, and chooses the latest reference', () => {
    const text = '`#12` 🚀 #123 then #456';
    assert.deepEqual(issueReferenceBeforeCaret(text, text.length), {
        start: 19,
        query: '456',
    });
});

test('does not look up digit-only colors as issues', () => {
    for (const [text, caret] of [
        ['#333333', 7],
        ['See #99999999', 13],
        ['Gray is #333333.', 15],
        ['#333333`code`', 7],
        ['#99999999`a b` done', 9],
    ] as const) {
        assert.equal(issueReferenceBeforeCaret(text, caret), null, text);
    }

    for (const [text, caret, query] of [
        ['See #123', 8, '123'],
        ['See (#333333', 12, '333333'],
        ['#333333.com', 7, '333333'],
        ['#333333`code', 7, '333333'],
    ] as const) {
        assert.equal(
            issueReferenceBeforeCaret(text, caret)?.query,
            query,
            text,
        );
    }
});
