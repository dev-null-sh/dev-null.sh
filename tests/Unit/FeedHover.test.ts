import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    createFeedHighlight,
    isDeliberateHover,
    isDimmedBy,
} from '../../resources/js/lib/feed-hover.ts';

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

await test('pointing at an author dims everyone else, even the commit under the pointer', () => {
    const commit = { id: 'a', author: { id: 'nuno' } };
    const other = { id: 'b', author: { id: 'taylor' } };
    const highlight = { commitId: 'b', authorId: 'nuno' };

    assert.equal(isDimmedBy(highlight, commit), false);
    assert.equal(isDimmedBy(highlight, other), true);
});

await test('pointing at a commit dims the rest of the feed', () => {
    const highlight = { commitId: 'a', authorId: null };

    assert.equal(
        isDimmedBy(highlight, { id: 'a', author: { id: 'x' } }),
        false,
    );
    assert.equal(isDimmedBy(highlight, { id: 'b', author: { id: 'x' } }), true);
    assert.equal(
        isDimmedBy(
            { commitId: null, authorId: null },
            { id: 'b', author: { id: 'x' } },
        ),
        false,
    );
});

await test('commits are only told about highlights that change something', () => {
    const highlight = createFeedHighlight();
    let notified = 0;
    const unsubscribe = highlight.subscribe(() => notified++);

    highlight.set({ commitId: 'a' });
    highlight.set({ commitId: 'a' });
    highlight.set({ authorId: null });
    assert.equal(notified, 1);

    highlight.set({ commitId: 'b', authorId: 'nuno' });
    assert.deepEqual(highlight.get(), { commitId: 'b', authorId: 'nuno' });
    assert.equal(notified, 2);

    unsubscribe();
    highlight.set({ commitId: null });
    assert.equal(notified, 2);
});
