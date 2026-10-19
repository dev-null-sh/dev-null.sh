/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { beforeEach, test, vi } from 'vite-plus/test';

let state: unknown[] = [];
let cursor = 0;
const auth = { requiresSignedCommits: true };
const signature = {
    nonce: 'nonce',
    credential_id: 'credential',
    client_data_json: 'client-data',
    authenticator_data: 'authenticator-data',
    signature: 'signature',
};
const signCommit = vi.fn(async () => signature);
const toast = { error: vi.fn() };

vi.doMock('react', () => ({
    ...React,
    useRef: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) {
            state[index] = { current: initial };
        }
        return state[index];
    },
    useState: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) {
            state[index] = initial;
        }
        return [
            state[index],
            (value: unknown) => {
                state[index] = value;
            },
        ];
    },
}));
vi.doMock('@inertiajs/react', () => ({
    usePage: () => ({ props: { auth } }),
}));
vi.doMock('sonner', () => ({ toast }));
vi.doMock('../../resources/js/lib/passkeys', () => ({ signCommit }));

const { useCommitSigning } =
    await import('../../resources/js/hooks/use-commit-signing');

const form = { current: { submit: vi.fn() } };
const signing = () => {
    cursor = 0;
    return useCommitSigning(form as never);
};

beforeEach(() => {
    vi.clearAllMocks();
    state = [];
    auth.requiresSignedCommits = true;
});

test('posts without a passkey when the account does not require signing', () => {
    auth.requiresSignedCommits = false;

    assert.deepEqual(signing().withSignature({ body: 'Hello' }), {
        body: 'Hello',
    });
    assert.equal(
        signing().beforeVisit(() => true),
        true,
    );
    assert.equal(
        signing().beforeVisit(() => false),
        false,
    );
    assert.equal(signCommit.mock.calls.length, 0);
});

test('stops the first submit, signs the draft, and submits again with the signature', async () => {
    signing().withSignature({
        body: 'Signed.',
        scheduled_at: '2026-10-20T09:00:00+00:00',
    });

    assert.equal(
        signing().beforeVisit(() => true),
        false,
    );
    await vi.waitFor(() =>
        assert.equal(form.current.submit.mock.calls.length, 1),
    );

    assert.deepEqual(signCommit.mock.calls[0], [
        {
            body: 'Signed.',
            quoted_commit_id: null,
            scheduled_at: '2026-10-20T09:00:00+00:00',
            poll: null,
            images: [],
        },
    ]);
    assert.deepEqual(signing().withSignature({ body: 'Signed.' }), {
        body: 'Signed.',
        signature,
    });

    const check = vi.fn(() => true);
    assert.equal(signing().beforeVisit(check), true);
    assert.equal(check.mock.calls.length, 0);
    assert.deepEqual(signing().withSignature({ body: 'Next.' }), {
        body: 'Next.',
    });
});

test('does not sign a draft that fails its own check', () => {
    assert.equal(
        signing().beforeVisit(() => false),
        false,
    );
    assert.equal(signCommit.mock.calls.length, 0);
});

test('keeps the draft when the passkey is dismissed', async () => {
    signCommit.mockRejectedValueOnce(
        Object.assign(new Error('Dismissed'), { name: 'NotAllowedError' }),
    );
    signing().withSignature({ body: 'Not yet.' });

    assert.equal(
        signing().beforeVisit(() => true),
        false,
    );
    await vi.waitFor(() => assert.equal(toast.error.mock.calls.length, 1));

    assert.deepEqual(toast.error.mock.calls[0], [
        'The commit was not signed, so it was not posted.',
    ]);
    assert.equal(form.current.submit.mock.calls.length, 0);
});
