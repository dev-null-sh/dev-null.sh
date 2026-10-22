import assert from 'node:assert/strict';
import { test } from 'node:test';
import { quoteRuns } from '../../resources/js/lib/block-quotes.ts';
import { bodySegments } from '../../resources/js/lib/code.ts';

const prose = (text: string) => ({
    isQuote: false,
    segments: [{ type: 'text', text }],
});
const quote = (text: string) => ({
    isQuote: true,
    segments: [{ type: 'text', text }],
});

await test('keeps a body without quotes as one run of prose', () => {
    const segments = bodySegments('just shipped it\nrun `ls` now');

    assert.deepEqual(quoteRuns(segments), [{ isQuote: false, segments }]);
});

await test('turns lines starting with a marker into one quote without the markers', () => {
    assert.deepEqual(quoteRuns(bodySegments('> shipped it\n> today')), [
        quote('shipped it\ntoday'),
    ]);
});

await test('keeps an empty line in a quote for a line that is only a marker', () => {
    assert.deepEqual(quoteRuns(bodySegments('> first\n>\n> second')), [
        quote('first\n\nsecond'),
    ]);
});

await test('drops the line breaks around a quote, like around a code block', () => {
    assert.deepEqual(quoteRuns(bodySegments('Look:\n> a\n> b\nnice')), [
        prose('Look:'),
        quote('a\nb'),
        prose('nice'),
    ]);
});

await test('drops a blank line around a quote, since its margin sets it apart', () => {
    assert.deepEqual(quoteRuns(bodySegments('a\n\n> b\n\nc')), [
        prose('a'),
        quote('b'),
        prose('c'),
    ]);
});

await test('keeps quoted paragraphs apart by a blank line in one quote', () => {
    assert.deepEqual(quoteRuns(bodySegments('> a\n\n> b')), [quote('a\n\nb')]);
});

await test('keeps the prose around an empty quote apart', () => {
    assert.deepEqual(quoteRuns(bodySegments('a\n>\nb')), [
        prose('a'),
        { isQuote: true, segments: [] },
        prose('b'),
    ]);
});

await test('keeps a blank line further from a quote', () => {
    assert.deepEqual(quoteRuns(bodySegments('> a\nb\n\nc')), [
        quote('a'),
        prose('b\n\nc'),
    ]);
});

await test('leaves a marker that is not followed by a space, or not at the start of a line, as prose', () => {
    for (const body of ['>_<', '>9000 commits', 'a > b', 'ship it\n=> done']) {
        assert.deepEqual(quoteRuns(bodySegments(body)), [prose(body)]);
    }
});

await test('keeps inline code inside a quote', () => {
    assert.deepEqual(quoteRuns(bodySegments('> run `ls` now\nok')), [
        {
            isQuote: true,
            segments: [
                { type: 'text', text: 'run ' },
                { type: 'code', code: 'ls' },
                { type: 'text', text: ' now' },
            ],
        },
        prose('ok'),
    ]);
});

await test('ends a quote at a line that starts with inline code', () => {
    assert.deepEqual(quoteRuns(bodySegments('> a\n`b` c')), [
        quote('a'),
        {
            isQuote: false,
            segments: [
                { type: 'code', code: 'b' },
                { type: 'text', text: ' c' },
            ],
        },
    ]);
});

await test('never quotes the lines of a code block', () => {
    assert.deepEqual(quoteRuns(bodySegments('> a\n```\n> b\n```\n> c')), [
        quote('a'),
        {
            isQuote: false,
            segments: [{ type: 'block', language: null, html: '&gt; b' }],
        },
        quote('c'),
    ]);
});

await test('keeps the line break that ends the prose', () => {
    assert.deepEqual(quoteRuns(bodySegments('> a\nb\n')), [
        quote('a'),
        prose('b\n'),
    ]);
});
