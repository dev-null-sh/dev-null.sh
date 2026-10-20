import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createDeletedResources } from '../../resources/js/lib/deleted-resources.ts';

void test('remembers deleted commits and pages separately', () => {
    const deleted = createDeletedResources();

    deleted.markCommit('commit');
    deleted.markPage('/@taylor');

    assert.equal(deleted.hasCommit('commit'), true);
    assert.equal(deleted.hasCommit('other'), false);
    assert.equal(deleted.hasPage('/@taylor'), true);
    assert.equal(deleted.hasPage('/@nuno'), false);
});

void test('notifies subscribers once per newly deleted resource', () => {
    const deleted = createDeletedResources();
    let notifications = 0;
    const unsubscribe = deleted.subscribe(() => notifications++);

    deleted.markCommit('commit');
    deleted.markCommit('commit');
    deleted.markPage('/@taylor');
    deleted.markPage('/@taylor');
    unsubscribe();
    deleted.markCommit('other');

    assert.equal(notifications, 2);
});
