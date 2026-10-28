/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement } from 'react';
import { afterEach, test, vi } from 'vite-plus/test';
import type { QuotedCommit as QuotedCommitData } from '../../resources/js/types';

let value: QuotedCommitData | null | undefined;
let effect: (() => () => void) | undefined;
let cleanup: (() => void) | undefined;

vi.doMock('react', () => ({
    ...React,
    useEffect: (callback: () => () => void) => {
        effect = callback;
    },
    useState: () => [value, (next: typeof value) => (value = next)],
}));

const { CommitLinkPreview } =
    await import('../../resources/js/components/commit-link-preview');
const { LinkPreview } =
    await import('../../resources/js/components/link-preview');
const url = '/@author/commits/123e4567-e89b-12d3-a456-426614174000/preview';

afterEach(() => {
    cleanup?.();
    cleanup = undefined;
    effect = undefined;
    value = undefined;
    vi.unstubAllGlobals();
});

function mount(): void {
    CommitLinkPreview({ url });
    cleanup = effect?.();
}

test('fetches current commit data once instead of polling cached link metadata', async () => {
    const commit = { id: 'linked' } as QuotedCommitData;
    const fetch = vi
        .fn<typeof globalThis.fetch>()
        .mockResolvedValue(new Response(JSON.stringify({ commit })));
    vi.stubGlobal('fetch', fetch);
    mount();

    await vi.waitFor(() => assert.deepEqual(value, commit));

    const rendered = CommitLinkPreview({ url }) as ReactElement<{
        commit: QuotedCommitData;
        label: string;
    }>;
    assert.deepEqual(rendered.props.commit, commit);
    assert.equal(rendered.props.label, 'Linked commit');
    assert.equal(fetch.mock.calls.length, 1);
    assert.equal(fetch.mock.calls[0][0], url);
    assert.equal(fetch.mock.calls[0][1]?.cache, 'no-store');
});

test('shows unavailable when the viewer cannot read the linked commit', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(new Response(null, { status: 404 })),
    );
    mount();

    await vi.waitFor(() => assert.equal(value, null));
});

test('handles a failed request without showing old preview content', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Offline')));
    mount();

    await vi.waitFor(() => assert.equal(value, null));
});

test('cancels the request when navigation removes the preview', async () => {
    let finish: ((response: Response) => void) | undefined;
    const fetch = vi
        .fn<typeof globalThis.fetch>()
        .mockImplementation(() => new Promise((resolve) => (finish = resolve)));
    vi.stubGlobal('fetch', fetch);
    mount();
    cleanup?.();
    finish?.(new Response(JSON.stringify({ commit: { id: 'old-page' } })));
    await new Promise((resolve) => setTimeout(resolve, 0));

    assert.equal(fetch.mock.calls[0][1]?.signal?.aborted, true);
    assert.equal(value, undefined);
});

test('uses the actual commit preview and stops at one nesting level', () => {
    const props = {
        refreshUrl: '/unused-link-metadata',
        preview: {
            url: 'https://dev-null.sh/@author/commits/123e4567-e89b-12d3-a456-426614174000',
            title: 'Old cached content',
            description: null,
            image_url: null,
            site_name: null,
            is_pending: true,
            commit_preview_url: url,
        },
    };

    const rendered = LinkPreview(props) as ReactElement<{ url: string }>;

    assert.equal(rendered.type, CommitLinkPreview);
    assert.equal(rendered.props.url, url);
    assert.equal(LinkPreview({ ...props, isStatic: true }), null);
});
