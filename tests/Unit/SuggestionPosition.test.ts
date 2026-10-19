import assert from 'node:assert/strict';
import { test } from 'node:test';
import { suggestionPosition } from '../../resources/js/lib/suggestion-position.ts';

await test('suggestions stay below the cursor when there is room', () => {
    const position = suggestionPosition(
        { left: 100, top: 100, bottom: 124 },
        { left: 0, top: 0, width: 1440, height: 900 },
        900,
    );

    assert.ok('top' in position);
    assert.equal(position.top, 130);
    assert.equal(position.maxHeight, 320);
});

await test('the keyboard makes suggestions open above the cursor in the visible viewport', () => {
    const position = suggestionPosition(
        { left: 300, top: 400, bottom: 424 },
        { left: 0, top: 200, width: 390, height: 300 },
        844,
    );

    assert.ok('bottom' in position);
    assert.equal(position.bottom, 450);
    assert.equal(position.maxHeight, 186);
    assert.equal(position.left, 94);
    assert.equal(844 - position.bottom, 394);
    assert.equal(844 - position.bottom - position.maxHeight, 208);
});

await test('suggestions fit a narrow, horizontally panned viewport', () => {
    const position = suggestionPosition(
        { left: 350, top: 110, bottom: 134 },
        { left: 100, top: 100, width: 240, height: 300 },
        844,
    );

    assert.ok('top' in position);
    assert.equal(position.width, 224);
    assert.equal(position.left, 108);
    assert.equal(position.top, 140);
    assert.equal(position.maxHeight, 252);
});
