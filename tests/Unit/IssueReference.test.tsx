/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'vite-plus/test';
import { issueReferenceBeforeCaret } from '../../resources/js/lib/issue-reference';

test('waits for a delimiter before automatically linking an exact number', () => {
    assert.deepEqual(issueReferenceBeforeCaret('See #72', 7), {
        start: 4,
        query: '72',
        complete: false,
    });
    assert.deepEqual(issueReferenceBeforeCaret('See #722 ', 9), {
        start: 4,
        query: '722',
        complete: true,
    });
    assert.deepEqual(issueReferenceBeforeCaret('See (#722).', 11), {
        start: 5,
        query: '722',
        complete: true,
    });
});

test('keeps a completed reference while its lookup is pending and typing continues', () => {
    const text = 'See #722 for details';
    assert.deepEqual(issueReferenceBeforeCaret(text, text.length), {
        start: 4,
        query: '722',
        complete: true,
    });
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
        complete: false,
    });
});
