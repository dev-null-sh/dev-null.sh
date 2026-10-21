/// <reference types="node" />
import assert from 'node:assert/strict';
import { afterEach, test, vi } from 'vite-plus/test';
import type { TimeoutFlash } from '../../resources/js/types/ui';

const visits: unknown[] = [];
const shown: TimeoutFlash[] = [];

vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    router: { visit: (route: unknown) => visits.push(route) },
}));
vi.doMock('../../resources/js/components/timeout-dialog.tsx', async () => ({
    ...(await vi.importActual(
        '../../resources/js/components/timeout-dialog.tsx',
    )),
    showTimeout: (timeout: TimeoutFlash) => shown.push(timeout),
}));

const { timeoutFrom } = await vi.importActual<
    typeof import('../../resources/js/components/timeout-dialog.tsx')
>('../../resources/js/components/timeout-dialog.tsx');
const { isRejectedSubmission } =
    await import('../../resources/js/lib/utils.ts');
const { ChatRequestError, renameOrg } =
    await import('../../resources/js/lib/chat.ts');

const timeout: TimeoutFlash = {
    reason: 'AI-generated posts',
    remaining: '3 hours',
    ends_at: '2026-10-20T15:00:00Z',
};

function respondWith(status: number, body: unknown): void {
    vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=token' });
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => new Response(JSON.stringify(body), { status })),
    );
}

afterEach(() => {
    visits.length = 0;
    shown.length = 0;
    vi.unstubAllGlobals();
});

test('a refusal names the timeout, whether its body is parsed or still raw', () => {
    assert.deepEqual(timeoutFrom(403, { timeout }), timeout);
    assert.deepEqual(timeoutFrom(403, JSON.stringify({ timeout })), timeout);
});

test('anything else is not a timeout, error pages from a proxy included', () => {
    assert.equal(timeoutFrom(502, '<html>Bad Gateway</html>'), null);
    assert.equal(timeoutFrom(403, '<html>Forbidden</html>'), null);
    assert.equal(timeoutFrom(403, ''), null);
    assert.equal(timeoutFrom(403, { message: 'Nope.' }), null);
    assert.equal(timeoutFrom(422, { timeout }), null);
});

test('a commit refused for a timeout counts as rejected, so its draft is kept', () => {
    assert.equal(isRejectedSubmission({ timeout }), true);
    assert.equal(
        isRejectedSubmission({
            toast: { type: 'error', message: 'Slow down.' },
        }),
        true,
    );
    assert.equal(
        isRejectedSubmission({
            toast: { type: 'success', message: 'Committed.' },
        }),
        false,
    );
    assert.equal(isRejectedSubmission({}), false);
});

test('a remote refused for a timeout opens the timeout dialog', async () => {
    respondWith(403, { message: "You're in timeout.", timeout });

    await assert.rejects(
        renameOrg({ id: 'org', name: null }, 'buy my course'),
        ChatRequestError,
    );

    assert.deepEqual(shown, [timeout]);
    assert.deepEqual(visits, []);
});

test('a remote refused for a ban sends the user to the banned page', async () => {
    respondWith(403, { message: 'Your account is banned.', banned: true });

    await assert.rejects(
        renameOrg({ id: 'org', name: null }, 'hello'),
        ChatRequestError,
    );

    assert.deepEqual(shown, []);
    assert.equal(visits.length, 1);
    assert.match(String((visits[0] as { url: string }).url), /\/banned$/);
});

test('other refusals neither open the dialog nor leave the page', async () => {
    respondWith(403, { message: 'Not a member.' });

    await assert.rejects(
        renameOrg({ id: 'org', name: null }, 'hello'),
        ChatRequestError,
    );

    assert.deepEqual(shown, []);
    assert.deepEqual(visits, []);
});
