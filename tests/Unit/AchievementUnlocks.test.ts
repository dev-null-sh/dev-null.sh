import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    completedAchievementIds,
    newlyCompletedAchievementIds,
} from '../../resources/js/lib/achievement-unlocks.ts';

const achievements = {
    badges: [
        { id: 'hello', unlocked: true },
        { id: 'streak', unlocked: false },
    ],
    ranks: [
        { level: 1, achieved: true },
        { level: 2, achieved: false },
    ],
};

await test('lists unlocked badges and achieved ranks', () => {
    assert.deepEqual(completedAchievementIds(achievements), [
        'badge:hello',
        'rank:1',
    ]);
});

await test('reports only achievements that were not already complete', () => {
    const next = completedAchievementIds({
        badges: [
            { id: 'hello', unlocked: true },
            { id: 'streak', unlocked: true },
        ],
        ranks: [
            { level: 1, achieved: true },
            { level: 2, achieved: true },
        ],
    });

    assert.deepEqual(
        newlyCompletedAchievementIds(
            completedAchievementIds(achievements),
            next,
        ),
        ['badge:streak', 'rank:2'],
    );
});

await test('stays quiet when nothing new is unlocked', () => {
    const completed = completedAchievementIds(achievements);

    assert.deepEqual(newlyCompletedAchievementIds(completed, completed), []);
});
