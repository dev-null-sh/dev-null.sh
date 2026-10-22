import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { ChangelogPullRequest } from '../../resources/js/lib/changelog.ts';
import {
    authorLane,
    changelogDayHeading,
    changelogDays,
} from '../../resources/js/lib/changelog.ts';

function pullRequest(
    number: number,
    mergedAt: string,
    login: string,
): ChangelogPullRequest {
    return {
        number,
        type: 'feat',
        breaking: false,
        title: `Change ${number}`,
        url: `https://github.com/dev-null-sh/dev-null.sh/pull/${number}`,
        sha: 'c897f86700000000000000000000000000000000',
        commit_url: 'https://github.com/dev-null-sh/dev-null.sh/commit/c897f86700000000000000000000000000000000',
        merged_at: mergedAt,
        author: {
            login,
            avatar: `https://avatars.githubusercontent.com/u/${number}`,
            username: null,
            mention: null,
            lane: null,
        },
    };
}

void test('groups merges by the reader’s calendar day, newest first, with who merged on each', () => {
    const days = changelogDays(
        [
            pullRequest(377, '2026-10-18T00:53:44Z', 'xHeaven'),
            pullRequest(310, '2026-10-17T23:26:43Z', 'simonhamp'),
            pullRequest(376, '2026-10-17T22:48:40Z', 'xHeaven'),
            pullRequest(304, '2026-10-17T21:15:34Z', 'ahosker'),
        ],
        'Europe/Budapest',
    );

    assert.deepEqual(
        days.map((day) => ({
            date: day.date,
            numbers: day.pullRequests.map((pullRequest) => pullRequest.number),
            authors: day.authors.map((author) => author.login),
        })),
        [
            { date: '2026-10-18', numbers: [377, 310, 376], authors: ['xHeaven', 'simonhamp'] },
            { date: '2026-10-17', numbers: [304], authors: ['ahosker'] },
        ],
    );
});

void test('puts a merge on the day it was in the reader’s time zone', () => {
    const merges = [pullRequest(377, '2026-10-18T03:30:00Z', 'xHeaven')];

    assert.equal(changelogDays(merges, 'Europe/Budapest')[0].date, '2026-10-18');
    assert.equal(changelogDays(merges, 'America/Los_Angeles')[0].date, '2026-10-17');
});

void test('keeps the lane an author chose on /dev/null', () => {
    assert.equal(authorLane({ login: 'xHeaven', lane: 4 }), 4);
    assert.equal(authorLane({ login: 'xHeaven', lane: '#ff2d20' }), '#ff2d20');
});

void test('gives an author who isn’t on /dev/null the same lane every time', () => {
    const lane = authorLane({ login: 'xHeaven', lane: null });

    assert.equal(authorLane({ login: 'xHeaven', lane: null }), lane);
    assert.ok(typeof lane === 'number' && Number.isInteger(lane) && lane >= 0 && lane < 6);
    assert.notEqual(
        new Set(
            ['xHeaven', 'simonhamp', 'ahosker', 'MrPunyapal', 'camilleislasse', 'robelsust'].map((login) =>
                authorLane({ login, lane: null }),
            ),
        ).size,
        1,
    );
});

void test('names the day relative to today in the reader’s time zone', () => {
    const now = new Date('2026-10-17T23:30:00Z');

    assert.deepEqual(changelogDayHeading('2026-10-18', 'Europe/Budapest', now), { title: 'Today', date: 'Oct 18' });
    assert.deepEqual(changelogDayHeading('2026-10-17', 'Europe/Budapest', now), { title: 'Yesterday', date: 'Oct 17' });
    assert.deepEqual(changelogDayHeading('2026-10-14', 'Europe/Budapest', now), { title: 'Wednesday', date: 'Oct 14' });
    assert.deepEqual(changelogDayHeading('2025-12-31', 'Europe/Budapest', now), { title: 'Wednesday', date: 'Dec 31, 2025' });
});
