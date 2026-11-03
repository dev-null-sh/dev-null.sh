import assert from 'node:assert/strict';
import { test } from 'node:test';
import { dictatedText } from '../../resources/js/lib/dictation.ts';

void test('dictated text is trimmed and starts a new word after the caret', () => {
    assert.equal(dictatedText('', ' shipped it '), 'shipped it');
    assert.equal(dictatedText('Finally', 'shipped it'), ' shipped it');
    assert.equal(dictatedText('Finally ', 'shipped it'), 'shipped it');
    assert.equal(dictatedText('Finally\n', 'shipped it'), 'shipped it');
    assert.equal(dictatedText('Finally', '   '), '');
});

void test('dictated text before a word is kept apart from it', () => {
    assert.equal(dictatedText('', 'Finally', 'shipped it'), 'Finally ');
    assert.equal(dictatedText('We ', 'finally', ' shipped'), 'finally');
    assert.equal(dictatedText('We', 'finally', 'shipped'), ' finally ');
});
