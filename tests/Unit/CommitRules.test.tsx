/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { test, vi } from 'vite-plus/test';

let auth: {
    user: { id: string } | null;
    mustReadCommitRules?: boolean;
} = { user: null };
const showings: boolean[] = [];

vi.doMock('react', () => ({
    ...React,
    useState: (initial: unknown) => [
        initial,
        (value: boolean) => showings.push(value),
    ],
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { auth } }),
}));

const { useCommitRules } =
    await import('../../resources/js/components/commit-rules.tsx');

test('a first-time poster is stopped by the rules on every attempt until they agree', () => {
    auth = { user: { id: 'newcomer' }, mustReadCommitRules: true };
    showings.length = 0;
    const rules = useCommitRules();

    assert.equal(rules.hasAccepted(), false);
    assert.equal(rules.beforeSubmit(), false);
    assert.equal(rules.beforeSubmit(), false);
    assert.deepEqual(showings, [true, true]);

    rules.agree();

    assert.equal(rules.hasAccepted(), true);
    assert.equal(rules.beforeSubmit(), true);
});

test('agreeing in one composer counts for the others on the page, which still have the stale prop', () => {
    auth = { user: { id: 'replier' }, mustReadCommitRules: true };
    useCommitRules().agree();

    const reply = useCommitRules();

    assert.equal(reply.hasAccepted(), true);
    assert.equal(reply.beforeSubmit(), true);
});

test('everyone who already read the rules commits straight away', () => {
    auth = { user: { id: 'regular' }, mustReadCommitRules: false };
    showings.length = 0;
    const rules = useCommitRules();

    assert.equal(rules.hasAccepted(), true);
    assert.equal(rules.beforeSubmit(), true);
    assert.deepEqual(showings, []);
});
