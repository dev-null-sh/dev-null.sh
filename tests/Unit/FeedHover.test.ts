import assert from 'node:assert/strict';
import { test } from 'node:test';
import { isDeliberateHover } from '../../resources/js/lib/feed-hover.ts';

await test('moving the pointer over a commit highlights it', () => {
    assert.equal(
        isDeliberateHover({ movementX: 4, movementY: 0 }, false),
        true,
    );
    assert.equal(
        isDeliberateHover({ movementX: 0, movementY: -2 }, false),
        true,
    );
});

await test('commits sliding under a still pointer are not highlighted', () => {
    assert.equal(
        isDeliberateHover({ movementX: 0, movementY: 0 }, false),
        false,
    );
});

await test('nudging the pointer while the feed scrolls does not highlight', () => {
    assert.equal(
        isDeliberateHover({ movementX: 3, movementY: 1 }, true),
        false,
    );
});
