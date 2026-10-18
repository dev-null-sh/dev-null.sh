/// <reference types="node" />
import assert from 'node:assert/strict';
import { beforeEach, test } from 'vite-plus/test';
import {
    readSharedState,
    refreshSharedState,
} from '../../resources/js/hooks/use-shared-state';
import { changeAuthorStars } from '../../resources/js/lib/author-stars';
import { commitStateKey } from '../../resources/js/lib/commit-state';

const key = commitStateKey('stars_received', 'author');

beforeEach(() => {
    refreshSharedState(new Map([[key, 13]]));
});

test('stars on two commits add up whichever response arrives last', () => {
    for (const order of [
        [0, 1],
        [1, 0],
    ]) {
        refreshSharedState(new Map([[key, 13]]));

        const stars = [
            changeAuthorStars('author', true),
            changeAuthorStars('author', true),
        ];

        assert.equal(readSharedState(key), 15);

        for (const index of order) {
            stars[index].settle(2, 3);
        }

        assert.equal(readSharedState(key), 15);
    }
});

test('a star and an unstar on different commits cancel out in any order', () => {
    const star = changeAuthorStars('author', true);
    const unstar = changeAuthorStars('author', false);

    unstar.settle(5, 4);
    star.settle(2, 3);

    assert.equal(readSharedState(key), 13);
});

test('counts stars others gave the same commit meanwhile', () => {
    changeAuthorStars('author', true).settle(2, 4);

    assert.equal(readSharedState(key), 15);
});

test('rolls back a failed star or unstar', () => {
    const star = changeAuthorStars('author', true);

    assert.equal(readSharedState(key), 14);
    star.revert();
    assert.equal(readSharedState(key), 13);

    const unstar = changeAuthorStars('author', false);

    assert.equal(readSharedState(key), 12);
    unstar.revert();
    assert.equal(readSharedState(key), 13);
});

test('leaves totals alone when no profile shows them', () => {
    changeAuthorStars('elsewhere', true).settle(0, 1);
    changeAuthorStars(null, true).settle(0, 1);

    assert.equal(
        readSharedState(commitStateKey('stars_received', 'elsewhere')),
        undefined,
    );
    assert.equal(readSharedState(key), 13);
});
