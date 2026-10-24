import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    drawnRect,
    frameAt,
    intersect,
    parseObjectPosition,
    peekMargin,
    peekRect,
    restingFrame,
} from '../../resources/js/lib/image-peek-geometry.ts';

const box = { left: 100, top: 50, width: 200, height: 100 };

test('reads an object position into offsets within the free space', () => {
    const free = { width: 40, height: 20 };

    assert.deepEqual(parseObjectPosition('50% 50%', free), { x: 20, y: 10 });
    assert.deepEqual(parseObjectPosition('0% 100%', free), { x: 0, y: 20 });
    assert.deepEqual(parseObjectPosition('8px 3px', free), { x: 8, y: 3 });
    assert.deepEqual(parseObjectPosition('', free), { x: 20, y: 10 });
    assert.deepEqual(parseObjectPosition('left top', free), { x: 20, y: 10 });
});

test('draws a stretched picture over its whole box', () => {
    assert.deepEqual(drawnRect(box, { width: 40, height: 40 }, 'fill'), box);
});

test('draws a picture that covers its box larger than the box, centred', () => {
    const drawn = drawnRect(box, { width: 100, height: 100 }, 'cover');

    assert.deepEqual(drawn, { left: 100, top: 0, width: 200, height: 200 });
});

test('draws a picture contained in its box smaller than the box, centred', () => {
    const drawn = drawnRect(box, { width: 100, height: 100 }, 'contain');

    assert.deepEqual(drawn, { left: 150, top: 50, width: 100, height: 100 });
});

test('draws a picture at its own size, or smaller if it does not fit', () => {
    const small = { width: 40, height: 20 };
    const large = { width: 400, height: 200 };

    assert.deepEqual(drawnRect(box, small, 'none'), {
        left: 180,
        top: 90,
        width: 40,
        height: 20,
    });
    assert.deepEqual(drawnRect(box, small, 'scale-down'), {
        left: 180,
        top: 90,
        width: 40,
        height: 20,
    });
    assert.deepEqual(drawnRect(box, large, 'scale-down'), box);
});

test('places a picture by its object position', () => {
    const drawn = drawnRect(box, { width: 100, height: 100 }, 'cover', '0% 0%');

    assert.deepEqual(drawn, { left: 100, top: 50, width: 200, height: 200 });
});

test('finds where two rectangles overlap, and when they do not', () => {
    assert.deepEqual(
        intersect(box, { left: 150, top: 0, width: 400, height: 80 }),
        { left: 150, top: 50, width: 150, height: 30 },
    );
    assert.equal(
        intersect(box, { left: 300, top: 50, width: 10, height: 10 }),
        null,
    );
});

test('centres a peek in the screen, with a margin all round', () => {
    const screen = { width: 1000, height: 800 };
    const natural = { width: 4000, height: 2000 };
    const peek = peekRect(screen, natural, { width: 200, height: 100 });

    assert.equal(peek.width, 1000 * (1 - 2 * peekMargin));
    assert.equal(peek.height, peek.width / 2);
    assert.equal(peek.left, (screen.width - peek.width) / 2);
    assert.equal(peek.top, (screen.height - peek.height) / 2);
});

test('does not blow a small picture up past its own size', () => {
    const peek = peekRect(
        { width: 1000, height: 800 },
        { width: 400, height: 300 },
        { width: 100, height: 75 },
    );

    assert.equal(peek.width, 400);
    assert.equal(peek.height, 300);
});

test('always grows a peek clearly bigger than where it started', () => {
    const peek = peekRect(
        { width: 1000, height: 800 },
        { width: 400, height: 300 },
        { width: 400, height: 300 },
    );

    assert.equal(peek.width, 640);
});

test('lays a tall picture out to fit the height of the screen', () => {
    const peek = peekRect(
        { width: 1000, height: 800 },
        { width: 1000, height: 3000 },
        { width: 200, height: 600 },
    );

    assert.equal(peek.height, 800 * (1 - 2 * peekMargin));
});

test('makes the peek look exactly like the picture on the page', () => {
    const peek = { left: 0, top: 0, width: 800, height: 400 };
    const drawn = { left: 100, top: 50, width: 200, height: 100 };
    const frame = frameAt(peek, drawn, drawn, 0);

    assert.equal(frame.transform, 'translate(100px, 50px) scale(0.25, 0.25)');
    assert.equal(frame.clipPath, 'inset(0px 0px 0px 0px round 0px)');
});

test('cuts the peek to what the page shows of a cropped picture', () => {
    const peek = { left: 0, top: 0, width: 800, height: 400 };
    const drawn = { left: 0, top: -40, width: 400, height: 200 };
    const visible = { left: 0, top: 0, width: 400, height: 120 };
    const frame = frameAt(peek, drawn, visible, 0);

    // 40px of the drawn picture is above the page's box, and 40px below it: 80px each at the peek's own scale.
    assert.equal(frame.clipPath, 'inset(80px 0px 80px 0px round 0px)');
});

test('opens a fully rounded picture into its corners smoothly', () => {
    const peek = { left: 0, top: 0, width: 400, height: 400 };
    const drawn = { left: 0, top: 0, width: 100, height: 100 };
    const circle = frameAt(peek, drawn, drawn, 9_999_999);

    // Half the picture's width, at the peek's scale, not millions of pixels.
    assert.equal(circle.clipPath, 'inset(0px 0px 0px 0px round 200px)');
});

test('rests in place, uncut, with its own rounded corners', () => {
    assert.deepEqual(restingFrame(12), {
        transform: 'translate(0px, 0px) scale(1, 1)',
        clipPath: 'inset(0px 0px 0px 0px round 12px)',
    });
});
