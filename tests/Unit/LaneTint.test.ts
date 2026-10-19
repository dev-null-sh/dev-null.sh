import assert from 'node:assert/strict';
import { test } from 'node:test';
import { laneColor, laneTint } from '../../resources/js/lib/utils.ts';

void test('tints a palette lane, which is a css variable rather than a hex colour', () => {
    assert.equal(
        laneTint(laneColor(2), 25),
        'color-mix(in oklab, var(--lane-2) 25%, transparent)',
    );
});

void test('tints a custom hex lane the same way', () => {
    assert.equal(
        laneTint(laneColor('#3b82f6'), 8),
        'color-mix(in oklab, #3b82f6 8%, transparent)',
    );
});
