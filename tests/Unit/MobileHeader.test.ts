import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    restingMobileHeader,
    scrolledMobileHeader,
} from '../../resources/js/lib/mobile-header.ts';
import type { MobileHeaderScroll } from '../../resources/js/lib/mobile-header.ts';

const screenHeight = 800;

const scrollThrough = (
    from: MobileHeaderScroll,
    positions: number[],
    isInFlow = false,
): MobileHeaderScroll =>
    positions.reduce(
        (scroll, y) => scrolledMobileHeader(scroll, y, isInFlow, screenHeight),
        from,
    );

await test('scrolling down keeps the header away', () => {
    assert.equal(
        scrollThrough(restingMobileHeader(0), [200, 600, 1200]).isShown,
        false,
    );
});

await test('scrolling up a little way brings the header back', () => {
    const scrolled = restingMobileHeader(1200);

    assert.equal(scrollThrough(scrolled, [1190, 1170]).isShown, false);
    assert.equal(scrollThrough(scrolled, [1190, 1170, 1150]).isShown, true);
});

await test('turning back down hides the header again', () => {
    const shown = scrollThrough(restingMobileHeader(1200), [1100]);

    assert.equal(shown.isShown, true);
    assert.equal(scrollThrough(shown, [1104]).isShown, true);
    assert.equal(scrollThrough(shown, [1104, 1120]).isShown, false);
});

await test('scrolling up from where it hid counts from the turn', () => {
    const hidden = scrollThrough(restingMobileHeader(1200), [1100, 1300]);

    assert.equal(hidden.isShown, false);
    assert.equal(scrollThrough(hidden, [1270]).isShown, false);
    assert.equal(scrollThrough(hidden, [1270, 1250]).isShown, true);
});

await test('at the top of the page the header is in the flow', () => {
    const shown = scrollThrough(restingMobileHeader(300), [200]);

    assert.equal(shown.isShown, true);
    assert.equal(scrollThrough(shown, [20], true).isShown, false);
});

await test('a jump of more than a screen leaves the header away', () => {
    assert.equal(
        scrollThrough(restingMobileHeader(3000), [1000]).isShown,
        false,
    );

    const shown = scrollThrough(restingMobileHeader(3000), [2900]);

    assert.equal(shown.isShown, true);
    assert.equal(scrollThrough(shown, [1500]).isShown, false);
});
