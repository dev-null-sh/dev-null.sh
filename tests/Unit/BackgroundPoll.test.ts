import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { HttpExceptionResponse } from '@inertiajs/core';
import { backgroundPollOptions } from '../../resources/js/lib/background-poll.ts';

function response(status: number): HttpExceptionResponse {
    return {
        status,
        headers: { 'content-type': 'text/plain' },
        data: 'upstream connect error or disconnect/reset before headers. reset reason: remote connection failure',
    };
}

for (const status of [502, 503, 504]) {
    void test(`keeps a background ${status} response out of the error dialog`, () => {
        const options = backgroundPollOptions({ only: ['newCommitsCount'] })();

        assert.equal(options.onHttpException?.(response(status)), false);
    });
}

void test('keeps a Cloudflare 520 HTML response out of the background error dialog', () => {
    const options = backgroundPollOptions({ only: ['newCommitsCount'] })();
    const cloudflareResponse: HttpExceptionResponse = {
        status: 520,
        headers: { 'content-type': 'text/html; charset=UTF-8' },
        data: '<!DOCTYPE html><html><body>Web server is returning an unknown error</body></html>',
    };

    assert.equal(options.onHttpException?.(cloudflareResponse), false);
});

void test('keeps connection failures out of the background error handler', () => {
    const options = backgroundPollOptions({})();

    assert.equal(options.onNetworkError?.(new Error('Failed to fetch')), false);
});

void test('leaves other HTTP failures to the normal error handler', () => {
    const options = backgroundPollOptions({})();

    for (const status of [401, 403, 404, 409, 419, 429, 500]) {
        assert.equal(options.onHttpException?.(response(status)), undefined);
    }
});

void test('preserves the deleted-page handler and its decision', () => {
    const statuses: number[] = [];
    const options = backgroundPollOptions({
        onHttpException: ({ status }) => {
            statuses.push(status);

            return status === 404 ? false : undefined;
        },
    })();

    assert.equal(options.onHttpException?.(response(404)), false);
    assert.equal(options.onHttpException?.(response(503)), false);
    assert.equal(options.onHttpException?.(response(500)), undefined);
    assert.deepEqual(statuses, [404, 503, 500]);
});

void test('notifies a caller about a connection failure', () => {
    const error = new Error('Failed to fetch');
    const errors: Error[] = [];
    const options = backgroundPollOptions({
        onNetworkError: (failure) => {
            errors.push(failure);
        },
    })();

    assert.equal(options.onNetworkError?.(error), false);
    assert.deepEqual(errors, [error]);
});

void test('reads current request data on every poll and preserves callbacks', () => {
    let since = 'first';
    const onFinish = () => undefined;
    const options = backgroundPollOptions(() => ({
        only: ['newCommitsCount'],
        data: { since },
        preserveUrl: true,
        onFinish,
    }));

    assert.deepEqual(options().data, { since: 'first' });
    since = 'latest';
    const next = options();
    assert.deepEqual(next.data, { since: 'latest' });
    assert.deepEqual(next.only, ['newCommitsCount']);
    assert.equal(next.preserveUrl, true);
    assert.equal(next.onFinish, onFinish);
});
