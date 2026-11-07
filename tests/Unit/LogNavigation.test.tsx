/// <reference types="node" />
import assert from 'node:assert/strict';
import type { InertiaLinkProps } from '@inertiajs/react';
import * as React from 'react';
import type { ComponentProps, ReactElement } from 'react';
import { afterAll, afterEach, test, vi } from 'vite-plus/test';

let pending: string | null = null;
let user: { id: string; username: string } | null = null;
const getPrefetching = vi.fn();
const getCached = vi.fn();
const flush = vi.fn();
const replaceProp = vi.fn();

vi.doMock('react', () => ({
    ...React,
    useEffect: () => {},
    useLayoutEffect: () => {},
    useRef: (initial: unknown) => ({ current: initial }),
    useState: (initial: unknown) => [
        initial,
        (value: unknown) => {
            pending = typeof value === 'function' ? value(pending) : value;
        },
    ],
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    router: { getPrefetching, getCached, flush, replaceProp },
    usePage: () => ({ props: { auth: { user, log: 'latest' } } }),
}));
vi.doMock('@/components/commit-composer-dialog', () => ({
    useCommitDialogClosedCount: () => 0,
}));
vi.doMock('@/hooks/use-composer-visibility', () => ({
    useComposerVisibility: () => {},
}));
vi.doMock('@/layouts/app-layout', () => ({ default: () => null }));

const { PageTabs } = await import('../../resources/js/components/page-tabs');
const { default: Show } = await import('../../resources/js/pages/log/show');
const { pageReadAt, pageReadTime } =
    await import('../../resources/js/lib/realtime');

const dispatchEvent = vi.fn();
vi.stubGlobal('window', {
    location: { pathname: '/log/trending' },
    dispatchEvent,
});
afterAll(() => vi.unstubAllGlobals());
afterEach(() => {
    pending = null;
    user = null;
    vi.clearAllMocks();
    getPrefetching.mockReset();
    getCached.mockReset();
    window.location.pathname = '/log/trending';
});

function logTabs() {
    const page = Show({ log: 'latest' } as ComponentProps<typeof Show>);
    const header = React.Children.toArray(
        page.props.children,
    )[1] as ReactElement<{
        children: React.ReactNode;
    }>;
    const tabs = React.Children.toArray(
        header.props.children,
    )[1] as ReactElement<ComponentProps<typeof PageTabs>>;

    return tabs.props;
}

function trendingLink() {
    const tabs = PageTabs(logTabs());

    return (
        React.Children.toArray(
            tabs.props.children,
        )[2] as ReactElement<InertiaLinkProps>
    ).props;
}

for (const viewer of [null, { id: 'viewer', username: 'viewer' }]) {
    test(`${viewer ? 'signed-in' : 'guest'} instant log visits let Inertia carry once props with their metadata`, () => {
        user = viewer;
        const tab = logTabs().tabs[2];
        const shared = {
            auth: { user: viewer, log: 'latest' },
            suggestedUsers: viewer
                ? [{ id: 'author', username: 'author' }]
                : undefined,
            flash: { status: 'Saved' },
        };

        assert.equal(tab.component, 'log/show');
        assert.equal(typeof tab.pageProps, 'function');
        if (typeof tab.pageProps !== 'function') {
            assert.fail('Expected instant page props');
        }

        const props = tab.pageProps(shared, shared);

        assert.equal(props.log, 'trending');
        assert.deepEqual(props.auth, { user: viewer, log: undefined });
        assert.equal(props.suggestedUsers, undefined);
        assert.deepEqual(props.flash, shared.flash);
        assert.equal(shared.auth.log, 'latest');
    });
}

test('clicking a pending prefetch exposes retry when that request fails', async () => {
    const response = Promise.withResolvers<void>();
    getPrefetching.mockReturnValue({ response: response.promise });
    const link = trendingLink();

    link.onBefore?.(
        {} as Parameters<NonNullable<InertiaLinkProps['onBefore']>>[0],
    );
    assert.equal(pending, 'Trending');

    response.reject(new Error('Network error'));
    await response.promise.catch(() => {});

    assert.equal(pending, null);
    assert.deepEqual(replaceProp.mock.calls, [['logLoadFailed', true]]);
});

test('a completed pending prefetch does not show a load error', async () => {
    const response = Promise.withResolvers<void>();
    getPrefetching.mockReturnValue({ response: response.promise });
    const link = trendingLink();

    link.onBefore?.(
        {} as Parameters<NonNullable<InertiaLinkProps['onBefore']>>[0],
    );
    response.resolve();
    await response.promise;
    link.onFinish?.(
        {} as Parameters<NonNullable<InertiaLinkProps['onFinish']>>[0],
    );

    assert.equal(pending, null);
    assert.equal(replaceProp.mock.calls.length, 0);
});

test('an old prefetch failure cannot mark a different log as failed', async () => {
    const response = Promise.withResolvers<void>();
    getPrefetching.mockReturnValue({ response: response.promise });
    const link = trendingLink();

    link.onBefore?.(
        {} as Parameters<NonNullable<InertiaLinkProps['onBefore']>>[0],
    );
    window.location.pathname = '/log/latest';
    pending = 'Latest';
    response.reject(new Error('Network error'));
    await response.promise.catch(() => {});

    assert.equal(pending, 'Latest');
    assert.equal(replaceProp.mock.calls.length, 0);
});

test('guest Following keeps the server redirect instead of opening an instant log', () => {
    const tab = logTabs().tabs[0];

    assert.equal(tab.component, undefined);
    assert.equal(tab.prefetch, false);
    assert.equal(tab.onLoadError?.(), undefined);
    assert.equal(replaceProp.mock.calls.length, 0);
});

test('a failed log visit uses the retry state instead of the default error dialog', () => {
    const link = trendingLink();

    assert.equal(
        link.onHttpException?.(
            {} as Parameters<
                NonNullable<InertiaLinkProps['onHttpException']>
            >[0],
        ),
        false,
    );
    assert.deepEqual(replaceProp.mock.calls, [['logLoadFailed', true]]);
    assert.deepEqual(flush.mock.calls, [
        [{ url: '/log/trending', method: 'get' }],
    ]);
});

for (const [visit, prefetched] of [
    ['shows a prefetched page', 'cached'],
    ['waits for its prefetch', 'in flight'],
    ['loads its own page', null],
] as const) {
    test(`an instant log visit that ${visit} ${prefetched === null ? 'was read once its request started' : 'catches up on what it missed since'}`, () => {
        getPrefetching.mockReturnValue(
            prefetched === 'in flight' ? { response: Promise.resolve() } : null,
        );
        getCached.mockReturnValue(
            prefetched === 'cached' ? { response: Promise.resolve() } : null,
        );
        pageReadAt(1);
        const link = trendingLink();

        link.onBefore?.(
            {} as Parameters<NonNullable<InertiaLinkProps['onBefore']>>[0],
        );

        if (prefetched === null) {
            const now = vi.spyOn(Date, 'now').mockReturnValue(5);
            link.onStart?.(
                {} as Parameters<NonNullable<InertiaLinkProps['onStart']>>[0],
            );
            now.mockRestore();
        }

        link.onSuccess?.(
            {} as Parameters<NonNullable<InertiaLinkProps['onSuccess']>>[0],
        );

        assert.equal(pageReadTime(), prefetched === null ? 5 : null);
    });
}

function currentTrendingTabs(commits?: { data: never[] }) {
    const page = Show({ log: 'trending', commits } as unknown as ComponentProps<
        typeof Show
    >);
    const header = React.Children.toArray(
        page.props.children,
    )[1] as ReactElement<{ children: React.ReactNode }>;

    return (
        React.Children.toArray(header.props.children)[1] as ReactElement<
            ComponentProps<typeof PageTabs>
        >
    ).props.tabs;
}

test('selecting the current Trending reads its ranking again in place, never from a prefetch', () => {
    const tabs = currentTrendingTabs({ data: [] });

    tabs[2]?.onClick?.(
        {} as Parameters<NonNullable<InertiaLinkProps['onClick']>>[0],
    );

    assert.deepEqual(flush.mock.calls, [
        [{ url: '/log/trending', method: 'get' }],
    ]);
    assert.equal(tabs[2]?.component, undefined);
    assert.equal(tabs[2]?.onLoadError, undefined);
    assert.equal(tabs[0]?.onClick, undefined);
    assert.equal(tabs[1]?.onClick, undefined);
    assert.equal(tabs[1]?.component, 'log/show');
});

test('selecting Trending again while it is still loading leaves that load alone', () => {
    const preventDefault = vi.fn();

    currentTrendingTabs()[2]?.onClick?.({
        button: 0,
        preventDefault,
    } as unknown as Parameters<NonNullable<InertiaLinkProps['onClick']>>[0]);

    assert.equal(preventDefault.mock.calls.length, 1);
    assert.equal(flush.mock.calls.length, 0);
});
