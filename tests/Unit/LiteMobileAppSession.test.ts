import assert from 'node:assert/strict';
import { test } from 'node:test';
import { isRefusedBySession } from '../../resources/js/lib/lite-mobile-app-session.ts';

const ORIGIN = 'https://dev-null.sh';

test('the site refusing a session or a CSRF token ends the session', () => {
    assert.equal(isRefusedBySession(401, `${ORIGIN}/remotes`, ORIGIN), true);
    assert.equal(isRefusedBySession(419, '/remotes', ORIGIN), true);
});

test('any other answer from the site leaves the session alone', () => {
    for (const status of [200, 302, 403, 404, 422, 429, 500]) {
        assert.equal(isRefusedBySession(status, '/remotes', ORIGIN), false);
    }
});

test("another site's refusal says nothing about the session", () => {
    assert.equal(
        isRefusedBySession(401, 'https://dev-null.shop/remotes', ORIGIN),
        false,
    );
    assert.equal(isRefusedBySession(401, '//example.com/a', ORIGIN), false);
});
