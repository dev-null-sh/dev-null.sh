import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    laneColor,
    laneStrokeStyle,
    laneTint,
} from '../../resources/js/lib/utils.ts';

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

void test('draws a reply stroke dashed in its lane', () => {
    const stroke = laneStrokeStyle(laneColor(3));

    assert.equal(stroke.backgroundColor, 'var(--lane-3)');
    assert.equal(stroke.backgroundImage, undefined);
    assert.equal(
        stroke.maskImage,
        'repeating-linear-gradient(to bottom, #000 0 2px, transparent 2px 4px)',
    );
});

void test('draws longer dashes when asked to', () => {
    const stroke = laneStrokeStyle(laneColor(3), { dash: 4 });

    assert.equal(
        stroke.maskImage,
        'repeating-linear-gradient(to bottom, #000 0 4px, transparent 4px 8px)',
    );
});

void test('ends the dashes on a whole gap when anchored to the end', () => {
    const stroke = laneStrokeStyle(laneColor(3), { anchor: 'end' });

    assert.equal(
        stroke.maskImage,
        'repeating-linear-gradient(to top, transparent 0 2px, #000 2px 4px)',
    );
});
