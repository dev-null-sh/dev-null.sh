/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterAll, beforeEach, test, vi } from 'vite-plus/test';
import type { LiveStream, User } from '../../resources/js/types';

let showPreview = false;

vi.doMock('react', () => ({
    ...React,
    useEffect: () => {},
    useLayoutEffect: () => {},
    useRef: (initial: unknown) => ({ current: initial }),
    useState: (initial: unknown) => [
        typeof initial === 'function'
            ? initial()
            : initial === null && showPreview
              ? { left: 0, top: 0 }
              : initial,
        () => {},
    ],
    useSyncExternalStore: (_subscribe: unknown, read: () => unknown) => read(),
}));
vi.doMock('react-dom', async () => ({
    ...(await vi.importActual('react-dom')),
    createPortal: (children: ReactNode) => children,
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ component: 'log/show', props: { auth: { user: null } } }),
}));

const { UserPreview } =
    await import('../../resources/js/components/user-preview');
const { LiveStreamCard } =
    await import('../../resources/js/components/live-stream-card');

vi.stubGlobal('window', { matchMedia: () => ({ matches: true }) });
vi.stubGlobal('document', { body: {} });
afterAll(() => vi.unstubAllGlobals());

beforeEach(() => {
    // The preview and who is live can't be read: the card shows what it was handed.
    vi.stubGlobal('fetch', async () => new Response('', { status: 503 }));
});

const person = (live: LiveStream): User =>
    ({
        id: 'streamer',
        name: 'Streamer',
        handle: 'streamer',
        avatar_url: null,
        lane: 1,
        live,
    }) as unknown as User;

function shownStream(user: User): LiveStream | undefined {
    showPreview = true;
    const preview = UserPreview({ user, children: 'Streamer' });
    showPreview = false;

    const panel = React.Children.toArray(preview.props.children)[1] as
        | ReactElement<{ children: ReactElement<object> }>
        | undefined;
    assert.ok(panel, 'the hover card is open');
    const content = panel.props.children;
    const rendered = (content.type as (props: object) => ReactNode)(
        content.props,
    );

    const find = (node: ReactNode): LiveStream | undefined =>
        React.Children.toArray(node)
            .filter((child) =>
                React.isValidElement<{
                    children?: ReactNode;
                    stream?: LiveStream;
                }>(child),
            )
            .map((child) =>
                child.type === LiveStreamCard
                    ? child.props.stream
                    : find(child.props.children),
            )
            .find(Boolean);

    return find(rendered);
}

test('a hover card opened before its preview answers shows no stream it was handed without when it runs out', () => {
    const stream = shownStream(
        person({
            url: 'https://twitch.tv/streamer',
            thumbnail_url: 'thumbnail',
        }),
    );

    assert.equal(stream, undefined);
});

test('a hover card opened before its preview answers shows a stream it was handed with its end, until then', () => {
    const live = {
        url: 'https://twitch.tv/streamer',
        thumbnail_url: 'thumbnail',
        ends_at: new Date(Date.now() + 3_600_000).toISOString(),
    };

    assert.deepEqual(shownStream(person(live)), live);
    assert.equal(
        shownStream(
            person({
                ...live,
                ends_at: new Date(Date.now() - 60_000).toISOString(),
            }),
        ),
        undefined,
    );
});
