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

void test('keeps a reply stroke solid when the lane does not change', () => {
    const stroke = laneStrokeStyle(laneColor(3));

    assert.equal(stroke.backgroundColor, 'var(--lane-3)');
    assert.equal(stroke.backgroundImage, undefined);
    assert.match(String(stroke.maskImage), /repeating-linear-gradient/);
});

void test('fades a reply stroke toward the next lane before the end of the path', () => {
    const stroke = laneStrokeStyle(laneColor(3), laneColor(4));

    assert.equal(stroke.backgroundColor, undefined);
    assert.equal(
        stroke.backgroundImage,
        'linear-gradient(to bottom, var(--lane-3) 0%, var(--lane-3) 50%, var(--lane-4) 100%)',
    );
});

void test('fades horizontal reply elbows the same way', () => {
    const stroke = laneStrokeStyle(laneColor(1), laneColor(5), 'horizontal');

    assert.equal(
        stroke.backgroundImage,
        'linear-gradient(to right, var(--lane-1) 0%, var(--lane-1) 50%, var(--lane-5) 100%)',
    );
});

void test('holds the first lane longer when asked to', () => {
    const stroke = laneStrokeStyle(laneColor(3), laneColor(4), { hold: 0.75 });

    assert.equal(
        stroke.backgroundImage,
        'linear-gradient(to bottom, var(--lane-3) 0%, var(--lane-3) 75%, var(--lane-4) 100%)',
    );
});

void test('draws longer dashes when asked to', () => {
    const stroke = laneStrokeStyle(laneColor(3), laneColor(3), { dash: 4 });

    assert.equal(
        stroke.maskImage,
        'repeating-linear-gradient(to bottom, #000 0 4px, transparent 4px 8px)',
    );
});

void test('ends the dashes on a whole gap when anchored to the end', () => {
    const stroke = laneStrokeStyle(laneColor(3), laneColor(3), {
        anchor: 'end',
    });

    assert.equal(
        stroke.maskImage,
        'repeating-linear-gradient(to top, transparent 0 2px, #000 2px 4px)',
    );
});

void test('keeps thread rails solid while fading between lanes', () => {
    const stroke = laneStrokeStyle(laneColor(3), laneColor(5), {
        dashed: false,
    });

    assert.equal(
        stroke.backgroundImage,
        'linear-gradient(to bottom, var(--lane-3) 0%, var(--lane-3) 50%, var(--lane-5) 100%)',
    );
    assert.equal(stroke.maskImage, undefined);
});
