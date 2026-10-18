import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    frequentReactions,
    isDoubleTap,
    quickReactions,
    summarizeReactions,
    withReactionUse,
} from '../../resources/js/lib/reactions.ts';

await test('starts the quick reactions as joy, check and heart', () => {
    assert.deepEqual(quickReactions([]), ['😂', '✅', '❤️']);
    assert.deepEqual(quickReactions([{ emoji: '🚀', count: 2 }]), [
        '😂',
        '✅',
        '❤️',
    ]);
});

await test('lets a much used emoji take the weakest slot, in place', () => {
    assert.deepEqual(
        quickReactions([
            { emoji: '😂', count: 4 },
            { emoji: '✅', count: 1 },
            { emoji: '🚀', count: 3 },
        ]),
        ['😂', '✅', '🚀'],
    );
    assert.deepEqual(
        quickReactions([
            { emoji: '🚀', count: 14 },
            { emoji: '😂', count: 11 },
            { emoji: '👀', count: 9 },
            { emoji: '✅', count: 5 },
            { emoji: '❤️', count: 1 },
        ]),
        ['😂', '👀', '🚀'],
    );
});

await test('keeps a slot until a newcomer clearly beats it', () => {
    assert.deepEqual(
        quickReactions([
            { emoji: '👀', count: 7 },
            { emoji: '😂', count: 6 },
            { emoji: '✅', count: 5 },
            { emoji: '❤️', count: 5 },
        ]),
        ['😂', '✅', '❤️'],
    );
});

await test('lists the most used reactions first', () => {
    assert.deepEqual(
        frequentReactions(
            [
                { emoji: '👀', count: 2 },
                { emoji: '🚀', count: 9 },
                { emoji: '😂', count: 2 },
            ],
            2,
        ),
        ['🚀', '👀'],
    );
});

await test('counts a reaction as it is added and taken back', () => {
    const usage = withReactionUse([{ emoji: '🚀', count: 1 }], '👀', 1);

    assert.deepEqual(usage, [
        { emoji: '🚀', count: 1 },
        { emoji: '👀', count: 1 },
    ]);
    assert.deepEqual(withReactionUse(usage, '🚀', -1), [
        { emoji: '👀', count: 1 },
    ]);
});

await test('groups reactions by emoji in the order they were first left', () => {
    assert.deepEqual(
        summarizeReactions(
            [
                { user_id: 'nuno', emoji: '😂' },
                { user_id: 'mark', emoji: '✅' },
                { user_id: 'mark', emoji: '😂' },
            ],
            'mark',
        ),
        [
            { emoji: '😂', count: 2, isMine: true, userIds: ['nuno', 'mark'] },
            { emoji: '✅', count: 1, isMine: true, userIds: ['mark'] },
        ],
    );
    assert.deepEqual(summarizeReactions(undefined, 'mark'), []);
});

await test('takes two quick taps close together as a double tap', () => {
    const first = { x: 100, y: 200, time: 1_000 };

    assert.equal(isDoubleTap(first, { x: 108, y: 195, time: 1_250 }), true);
    assert.equal(isDoubleTap(first, { x: 100, y: 200, time: 1_400 }), false);
    assert.equal(isDoubleTap(first, { x: 160, y: 200, time: 1_100 }), false);
    assert.equal(isDoubleTap(null, first), false);
});
