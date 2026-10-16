/// <reference types="node" />
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const source = readFileSync(
    new URL('../../resources/js/components/commit-card.tsx', import.meta.url),
    'utf8',
);
const previews = source.match(/<UserPreview\b[^>]*>/g) ?? [];

void test('timeline author previews use the hovered name instead of the graph avatar as their position trigger', () => {
    const authorPreview = previews.find((preview) =>
        preview.includes('items-baseline'),
    );

    assert.ok(authorPreview);
    assert.doesNotMatch(authorPreview, /\banchor\s*=/);
});

void test('timeline graph avatar previews retain their avatar anchor', () => {
    const avatarPreview = previews.find((preview) =>
        preview.includes('className="flex"'),
    );

    assert.ok(avatarPreview);
    assert.match(avatarPreview, /\banchor=\{nodeRef\}/);
});
