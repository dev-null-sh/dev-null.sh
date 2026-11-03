import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { HttpExceptionResponse } from '@inertiajs/core';
import {
    createDeletedResources,
    isPageGone,
} from '../../resources/js/lib/deleted-resources.ts';

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

function notFound(data: HttpExceptionResponse['data']): HttpExceptionResponse {
    return { status: 404, headers: {}, data };
}

void test('reads a 404 as the open page being gone when it answers another page or no page at all', () => {
    assert.equal(
        isPageGone(notFound({ component: 'error' }), 'user/show'),
        true,
    );
    assert.equal(
        isPageGone(notFound({ component: 'profile/lookup' }), 'user/show'),
        true,
    );
    assert.equal(
        isPageGone(notFound('<!DOCTYPE html><html></html>'), 'user/show'),
        true,
    );
});

void test('reads a page that is a 404 from the start, like the guest sign-in prompt for a handle, as still there', () => {
    assert.equal(
        isPageGone(notFound({ component: 'profile/lookup' }), 'profile/lookup'),
        false,
    );
    assert.equal(
        isPageGone(
            { status: 500, headers: {}, data: { component: 'error' } },
            'user/show',
        ),
        false,
    );
});
