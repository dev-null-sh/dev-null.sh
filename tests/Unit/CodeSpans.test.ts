import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    bodySegments,
    mapTypedProse,
    typedProse,
} from '../../resources/js/lib/code.ts';

for (const lineBreak of ['\n', '\r\n']) {
    for (const fence of ['````', '`````']) {
        void test(`keeps shorter fences inside a ${fence.length}-backtick block with ${JSON.stringify(lineBreak)}`, () => {
            const code = ['```', 'hello', '```'].join(lineBreak);
            const body = ['before', `${fence}markdown`, code, fence, 'after'].join(
                lineBreak,
            );

            assert.deepEqual(bodySegments(body), [
                { type: 'text', text: 'before' },
                {
                    type: 'block',
                    language: 'markdown',
                    html: '```\nhello\n```',
                },
                { type: 'text', text: 'after' },
            ]);
            assert.deepEqual(typedProse(body), [
                `before${lineBreak}`,
                `${lineBreak}after`,
            ]);
            assert.equal(
                mapTypedProse(body, (prose) => prose.toUpperCase()),
                ['BEFORE', `${fence}markdown`, code, fence, 'AFTER'].join(
                    lineBreak,
                ),
            );
        });
    }
}

void test('keeps all content after a shorter fence in an unfinished draft block', () => {
    const code = '````markdown\n```\nhello\n```\n:fire: @example';
    const body = `before\n${code}`;

    assert.deepEqual(typedProse(body), ['before\n', '']);
    assert.equal(
        mapTypedProse(body, (prose) => prose.toUpperCase()),
        `BEFORE\n${code}`,
    );
});

void test('accepts a longer closing fence and keeps later code blocks separate', () => {
    assert.deepEqual(
        bodySegments('```\nfirst\n````\nbetween `inline`\n```text\nsecond\n```'),
        [
            { type: 'block', language: null, html: 'first' },
            { type: 'text', text: 'between ' },
            { type: 'code', code: 'inline' },
            { type: 'block', language: 'text', html: 'second' },
        ],
    );
});

void test('keeps an empty block with a longer fence', () => {
    assert.deepEqual(bodySegments('````\n````'), [
        { type: 'block', language: null, html: '' },
    ]);
});

void test('escapes HTML inside nested fences', () => {
    assert.deepEqual(bodySegments('````markdown\n```\n<script>\n```\n````'), [
        {
            type: 'block',
            language: 'markdown',
            html: '```\n&lt;script&gt;\n```',
        },
    ]);
});
