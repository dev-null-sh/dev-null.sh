import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    isBlockedGifText,
    isSafeGiphyRating,
} from '../../resources/js/lib/gif-providers/safety.ts';

test('only g-rated giphy results are safe', () => {
    assert.equal(isSafeGiphyRating('g'), true);
    assert.equal(isSafeGiphyRating('G'), true);
    assert.equal(isSafeGiphyRating('pg'), false);
    assert.equal(isSafeGiphyRating('pg-13'), false);
    assert.equal(isSafeGiphyRating('r'), false);
    assert.equal(isSafeGiphyRating('y'), false);
    assert.equal(isSafeGiphyRating(undefined), false);
});

test('blocked words match whole words in any case', () => {
    assert.equal(isBlockedGifText('PORN star'), true);
    assert.equal(isBlockedGifText('Essex'), false);
    assert.equal(isBlockedGifText(null, 'Hello', 'NSFW'), true);
    assert.equal(isBlockedGifText('gory scene'), true);
    assert.equal(isBlockedGifText('cocaine'), true);
    assert.equal(isBlockedGifText('hello greeting'), false);
});
