import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createCommitStarMutations } from '../../resources/js/lib/commit-star-mutations.ts';

const stars = { id: 'commit', starred: false, stars_count: 2 };

void test('applies a poll when no star mutation has started', () => {
    const mutations = createCommitStarMutations();
    const received: (typeof stars)[] = [];
    const update = mutations.prepare(stars.id, (state) => received.push(state));

    assert.ok(update);
    update(stars);
    assert.deepEqual(received, [stars]);
});

void test('a click on either copy rejects every older poll, even after completion', () => {
    const mutations = createCommitStarMutations();
    const received: (typeof stars)[] = [];
    const update = (state: typeof stars) => received.push(state);
    const feedPoll = mutations.prepare(stars.id, update);
    const threadPoll = mutations.prepare(stars.id, update);
    const finish = mutations.begin(stars.id);

    assert.ok(feedPoll);
    assert.ok(threadPoll);
    assert.ok(finish);
    feedPoll(stars);
    finish();
    threadPoll(stars);
    assert.deepEqual(received, []);
});

void test('duplicate controls cannot mutate or poll a commit while its mutation is pending', () => {
    const mutations = createCommitStarMutations();
    const finish = mutations.begin(stars.id);

    assert.ok(finish);
    assert.equal(mutations.begin(stars.id), null);
    assert.equal(
        mutations.prepare(stars.id, () => assert.fail()),
        null,
    );
    finish();
    assert.ok(mutations.begin(stars.id));
});

void test('polling resumes after a mutation finishes or rolls back', () => {
    const mutations = createCommitStarMutations();
    const finish = mutations.begin(stars.id);
    const received: (typeof stars)[] = [];

    assert.ok(finish);
    finish();
    const update = mutations.prepare(stars.id, (state) => received.push(state));
    assert.ok(update);
    update(stars);
    assert.deepEqual(received, [stars]);
});

void test('a mutation leaves polls for other commits and other layouts independent', () => {
    const mutations = createCommitStarMutations();
    const otherLayout = createCommitStarMutations();
    const received: (typeof stars)[] = [];
    const update = mutations.prepare('other-commit', (state) =>
        received.push(state),
    );

    assert.ok(mutations.begin(stars.id));
    assert.ok(otherLayout.begin(stars.id));
    assert.ok(update);
    update({ ...stars, id: 'other-commit' });
    assert.deepEqual(received, [{ ...stars, id: 'other-commit' }]);
});
