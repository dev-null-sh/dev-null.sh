/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { activeMention, insertMention } from './mentions';

void test('opens on @ at a mention boundary and follows the caret', () => {
    assert.deepEqual(activeMention('@', 1), { start: 0, end: 1, query: '' });
    assert.deepEqual(activeMention('Thanks (@dev-one), ship it.', 10), {
        start: 8,
        end: 16,
        query: 'd',
    });
    assert.equal(activeMention('Select @dev', 7, 11), null);
    for (const value of [
        'mail@dev',
        'https://example.com/@dev',
        'example.com/(@dev',
        'user@@dev',
        'word@dev',
        'https://@dev',
    ]) {
        assert.equal(activeMention(value, value.length), null, value);
    }
});

void test('replaces only the active fragment and preserves suffix text', () => {
    const value = 'Thanks @de-one, @someone!';
    const fragment = activeMention(value, 10)!;
    assert.deepEqual(insertMention(value, fragment, 'developer', 280), {
        value: 'Thanks @developer , @someone!',
        caret: 18,
    });
    assert.deepEqual(
        insertMention(
            '@de next',
            { start: 0, end: 3, query: 'de' },
            'dev',
            280,
        ),
        { value: '@dev next', caret: 5 },
    );
});

void test('never truncates the body or exceeds its character limit', () => {
    assert.equal(
        insertMention(
            'x'.repeat(274) + ' @de',
            { start: 275, end: 278, query: 'de' },
            'developer',
            280,
        ),
        null,
    );
    assert.deepEqual(
        insertMention('@de', { start: 0, end: 3, query: 'de' }, 'dev', 5),
        { value: '@dev ', caret: 5 },
    );
});
