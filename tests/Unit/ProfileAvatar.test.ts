import assert from 'node:assert/strict';
import { test } from 'node:test';
import { profileAvatarPosition } from '../../resources/js/lib/profile-avatar.ts';

const target = { left: 315, top: 11.5, width: 40 };
const source = { left: 323, top: 24, bottom: 104, width: 80 };

void test('keeps the full-size avatar in its profile position before scrolling', () => {
    assert.deepEqual(profileAvatarPosition(source, target, 64), {
        progress: 0,
        left: 323,
        top: 24,
        size: 80,
    });
});

void test('moves continuously across the former one-pixel avatar swap', () => {
    const positions = [39, 40, 41, 42, 45, 46].map((scroll) =>
        profileAvatarPosition(
            {
                ...source,
                top: source.top - scroll,
                bottom: source.bottom - scroll,
            },
            target,
            64,
        ),
    );

    for (let index = 1; index < 4; index++) {
        assert.ok(
            Math.abs(positions[index].size - positions[index - 1].size) < 1,
        );
        assert.ok(
            Math.abs(positions[index].left - positions[index - 1].left) < 1,
        );
        assert.ok(
            Math.abs(positions[index].top - positions[index - 1].top) < 1,
        );
    }

    assert.equal(positions.at(-1)?.progress, 1);
    assert.equal(positions.at(-1)?.size, 40);
});

void test('settles on the feed avatar after the entire original ring passes the header', () => {
    for (const scroll of [46, 47, 300, 1000]) {
        assert.deepEqual(
            profileAvatarPosition(
                {
                    ...source,
                    top: source.top - scroll,
                    bottom: source.bottom - scroll,
                },
                target,
                64,
            ),
            { progress: 1, left: 315, top: 11.5, size: 40 },
        );
    }
});

void test('follows the mobile header before it sticks', () => {
    const mobileSource = { left: 16, top: 80, bottom: 160, width: 80 };
    const mobileTarget = { left: 12, top: 67.5, width: 40 };

    assert.deepEqual(profileAvatarPosition(mobileSource, mobileTarget, 120), {
        progress: 0,
        left: 16,
        top: 80,
        size: 80,
    });

    assert.deepEqual(
        profileAvatarPosition(
            { ...mobileSource, top: 60, bottom: 140 },
            { ...mobileTarget, top: 47.5 },
            100,
        ),
        { progress: 0, left: 16, top: 60, size: 80 },
    );
});

void test('uses an instant handoff for reduced motion', () => {
    assert.equal(
        profileAvatarPosition(
            { ...source, top: 1, bottom: 81 },
            target,
            64,
            true,
        ).size,
        80,
    );
    assert.deepEqual(
        profileAvatarPosition(
            { ...source, top: -22, bottom: 58 },
            target,
            64,
            true,
        ),
        { progress: 1, left: 315, top: 11.5, size: 40 },
    );
});
