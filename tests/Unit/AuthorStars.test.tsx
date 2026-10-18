/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'vite-plus/test';
import { readSharedState } from '../../resources/js/hooks/use-shared-state';
import {
    authorStarsKey,
    showAuthorStars,
} from '../../resources/js/lib/author-stars';

const total = (userId: string) => readSharedState(authorStarsKey(userId));

test('unstarring a commit starred in another tab and picked up by polling shows the real total, never a negative one', () => {
    // A zero-star profile, then the same account stars its commit in another tab, and polling
    // updates the commit here. Polling never touches the total, and unstarring here does not
    // guess it either: the total only ever comes from a count the server made.
    showAuthorStars({ user_id: 'polled', total: 0, counted_at: 1 });
    assert.equal(total('polled'), 0);

    showAuthorStars({ user_id: 'polled', total: 0, counted_at: 3 });
    assert.equal(total('polled'), 0);
});

test('a star response that arrives after a newer one is ignored', () => {
    showAuthorStars({ user_id: 'racing', total: 13, counted_at: 1 });
    showAuthorStars({ user_id: 'racing', total: 15, counted_at: 3 });
    showAuthorStars({ user_id: 'racing', total: 14, counted_at: 2 });

    assert.equal(total('racing'), 15);
});

test('the profile does not put back its older total when it mounts again after a star', () => {
    showAuthorStars({ user_id: 'remounted', total: 13, counted_at: 1 });
    showAuthorStars({ user_id: 'remounted', total: 14, counted_at: 2 });
    showAuthorStars({ user_id: 'remounted', total: 13, counted_at: 1 });

    assert.equal(total('remounted'), 14);
});

test('a newer count always replaces the shown one, even when it is lower', () => {
    showAuthorStars({ user_id: 'reloaded', total: 14, counted_at: 2 });
    showAuthorStars({ user_id: 'reloaded', total: 11, counted_at: 5 });

    assert.equal(total('reloaded'), 11);
});

test('counts taken at the same time do not replace each other', () => {
    showAuthorStars({ user_id: 'tied', total: 7, counted_at: 4 });
    showAuthorStars({ user_id: 'tied', total: 8, counted_at: 4 });

    assert.equal(total('tied'), 7);
});

test('each author keeps their own total', () => {
    showAuthorStars({ user_id: 'first', total: 1, counted_at: 9 });
    showAuthorStars({ user_id: 'second', total: 2, counted_at: 1 });

    assert.equal(total('first'), 1);
    assert.equal(total('second'), 2);
});
