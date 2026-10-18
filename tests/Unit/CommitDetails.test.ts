/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    bodySegments,
    hasCodeBlock,
    mapTypedProse,
    typedProse,
} from '../../resources/js/lib/code.ts';

void test('splits a details block into its summary and the prose it holds', () => {
    assert.deepEqual(
        bodySegments(
            '<details>\n<summary>Why it took all day</summary>\n\nIt was DNS.\n</details>',
        ),
        [
            {
                type: 'details',
                summary: 'Why it took all day',
                segments: [{ type: 'text', text: 'It was DNS.' }],
            },
        ],
    );
});

void test('cuts the code a details block holds, dropping the lines around it', () => {
    assert.deepEqual(
        bodySegments(
            '<details>\n<summary>How</summary>\n\nLook:\n```php\necho 1;\n```\n</details>',
        ),
        [
            {
                type: 'details',
                summary: 'How',
                segments: [
                    { type: 'text', text: 'Look:' },
                    { type: 'block', language: 'php', html: 'echo 1;' },
                ],
            },
        ],
    );
});

void test('keeps the prose around a details block without the line break against it', () => {
    assert.deepEqual(
        bodySegments('a\n<details>\n<summary>Why</summary>\n\nb\n</details>\nc'),
        [
            { type: 'text', text: 'a' },
            {
                type: 'details',
                summary: 'Why',
                segments: [{ type: 'text', text: 'b' }],
            },
            { type: 'text', text: 'c' },
        ],
    );
});

void test('finds a code block inside a details block, and none where there is none', () => {
    assert.equal(
        hasCodeBlock(
            bodySegments(
                '<details>\n<summary>How</summary>\n\n```php\necho 1;\n```\n</details>',
            ),
        ),
        true,
    );

    assert.equal(
        hasCodeBlock(
            bodySegments(
                '<details>\n<summary>Why</summary>\n\n`echo 1;` says it all.\n</details>',
            ),
        ),
        false,
    );
});

void test('reaches the summary and the prose of a details block, but not its code', () => {
    assert.equal(
        mapTypedProse(
            '<details>\n<summary>a :fire:</summary>\n\nb `:heart:` c\n</details>',
            (prose) => prose.toUpperCase(),
        ),
        '<details>\n<summary>A :FIRE:</summary>\n\nB `:heart:` C\n</details>',
    );
});

void test('escapes the code a details block holds and keeps its summary as written', () => {
    assert.deepEqual(
        bodySegments(
            '<details>\n<summary><script>alert(1)</script></summary>\n\n```html\n<img src=x onerror=alert(1)>\n```\n</details>',
        ),
        [
            {
                type: 'details',
                summary: '<script>alert(1)</script>',
                segments: [
                    {
                        type: 'block',
                        language: 'html',
                        html: '&lt;img src=x onerror=alert(1)&gt;',
                    },
                ],
            },
        ],
    );
});

void test('keeps the prose of a details block and drops its code', () => {
    assert.deepEqual(
        typedProse(
            '<details>\n<summary>Why</summary>\n\nsee `x` and read on\n</details>',
        ).filter((prose) => prose !== ''),
        ['Why', '\nsee ', ' and read on\n'],
    );
});
