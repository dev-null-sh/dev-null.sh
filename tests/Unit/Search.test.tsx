/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';

let state: unknown[] = [];
let cursor = 0;
let effects: {
    deps: React.DependencyList;
    cleanup?: () => void;
}[] = [];
let pending: (() => void)[] = [];

vi.doMock('react', () => ({
    ...React,
    useState: (initial: unknown) => {
        const index = cursor++;

        if (!(index in state)) {
            state[index] = initial;
        }

        return [
            state[index],
            (value: unknown) => {
                state[index] =
                    typeof value === 'function' ? value(state[index]) : value;
            },
        ];
    },
    useRef: () => ({ current: null }),
    useEffect: (
        effect: () => (() => void) | undefined,
        deps: React.DependencyList,
    ) => {
        const index = cursor++;
        const previous = effects[index];

        if (previous?.deps.every((value, i) => Object.is(value, deps[i]))) {
            return;
        }

        previous?.cleanup?.();
        pending.push(() => {
            effects[index] = { deps, cleanup: effect() };
        });
    },
    useSyncExternalStore: (
        _subscribe: unknown,
        getSnapshot: () => unknown,
    ) => getSnapshot(),
}));
vi.doMock('../../resources/js/hooks/use-recent-searches', () => ({
    useRecentSearches: () => [],
    rememberSearch: vi.fn(),
}));
vi.doMock('@inertiajs/react', () => ({
    Head: () => null,
    router: { get: vi.fn() },
    usePage: () => ({ props: { auth: { user: null } } }),
}));
vi.doMock('../../resources/js/layouts/app-layout', () => ({
    default: () => null,
}));
vi.doMock('../../resources/js/components/commit-card', () => ({
    CommitCard: ({ commit }: { commit: { body: string } }) => (
        <article>{commit.body}</article>
    ),
}));
vi.doMock('../../resources/js/components/commit-feed', () => ({
    CommitSkeleton: () => <div>Loading commit</div>,
    EmptyFeed: ({
        command,
        children,
    }: {
        command: string;
        children: ReactNode;
    }) => (
        <div>
            {command}
            {children}
        </div>
    ),
}));

const { default: Index } =
    await import('../../resources/js/pages/search/index');
const { SearchError } =
    await import('../../resources/js/components/search-error');
const fetchMock = vi.fn<typeof fetch>();

function render(query = 'database') {
    cursor = 0;
    const tree = Index({ query });
    const html = renderToStaticMarkup(tree).replaceAll('<!-- -->', '');
    pending.splice(0).forEach((effect) => effect());

    return { tree, html };
}

function findRetry(node: ReactNode): (() => void) | undefined {
    if (!React.isValidElement(node)) {
        return;
    }

    const element = node as ReactElement<{
        children?: ReactNode;
        onRetry: () => void;
    }>;

    if (element.type === SearchError) {
        return element.props.onRetry;
    }

    for (const child of React.Children.toArray(element.props.children)) {
        const retry = findRetry(child);

        if (retry) {
            return retry;
        }
    }
}

async function settle() {
    await vi.advanceTimersByTimeAsync(0);
}

beforeEach(() => {
    state = [];
    effects = [];
    pending = [];
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    vi.useFakeTimers();
});

afterEach(() => {
    effects.forEach((effect) => effect.cleanup?.());
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

for (const status of [429, 503]) {
    test(`announces HTTP ${status} as a recoverable failure without claiming zero matches`, async () => {
        fetchMock.mockResolvedValue(new Response('{}', { status }));
        render();
        await settle();
        const { html } = render();

        assert.match(html, /role="alert"/);
        assert.match(html, /Try again/);
        assert.match(
            html,
            status === 429
                ? /too many searches/
                : /could not complete the search/,
        );
        assert.doesNotMatch(html, /0 matches|nothing close|Searching commits/);
    });
}

test('offers recovery when the network request fails', async () => {
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'));
    render();
    await settle();

    assert.match(render().html, /could not complete the search/);
});

test('retries the same query and renders a genuine empty success', async () => {
    fetchMock.mockResolvedValueOnce(new Response('{}', { status: 503 }));
    fetchMock.mockResolvedValueOnce(Response.json({ commits: [] }));
    render();
    await settle();
    const retry = findRetry(render().tree);
    assert.ok(retry);
    retry();
    render();
    await settle();
    const { html } = render();

    assert.equal(fetchMock.mock.calls.length, 2);
    assert.equal(fetchMock.mock.calls[0][0], fetchMock.mock.calls[1][0]);
    assert.match(html, /value="database"/);
    assert.match(html, /0 matches/);
    assert.match(html, /nothing close to/);
    assert.doesNotMatch(html, /role="alert"/);
});

test('preserves and identifies previous results when a new query fails', async () => {
    fetchMock.mockResolvedValueOnce(
        Response.json({ commits: [{ id: 'first', body: 'A useful result' }] }),
    );
    fetchMock.mockRejectedValueOnce(new TypeError('Failed to fetch'));
    render();
    await settle();
    render('testing');
    await settle();
    const { html } = render('testing');

    assert.match(html, /role="alert"/);
    assert.match(html, /A useful result/);
    assert.match(html, /Showing previous results for “database”/);
    assert.doesNotMatch(html, /nothing close to/);
});

test('does not present an earlier empty result as the outcome of a failed query', async () => {
    fetchMock.mockResolvedValueOnce(Response.json({ commits: [] }));
    fetchMock.mockResolvedValueOnce(new Response('{}', { status: 503 }));
    render();
    await settle();
    render('testing');
    await settle();
    const { html } = render('testing');

    assert.match(html, /role="alert"/);
    assert.doesNotMatch(html, /0 matches|nothing close to/);
});

test('ignores a late successful response from an aborted query', async () => {
    let finish: ((value: Response) => void) | undefined;
    fetchMock.mockImplementationOnce(
        () => new Promise((resolve) => (finish = resolve)),
    );
    fetchMock.mockResolvedValueOnce(Response.json({ commits: [] }));
    render();
    render('testing');
    await settle();
    assert.ok(finish);
    finish(Response.json({ commits: [{ id: 'stale', body: 'Stale result' }] }));
    await settle();
    const { html } = render('testing');

    assert.match(html, /nothing close to &#x27;testing&#x27;/);
    assert.doesNotMatch(html, /Stale result|role="alert"/);
});

test('ignores a late error from an aborted query', async () => {
    let fail: ((reason: Error) => void) | undefined;
    fetchMock.mockImplementationOnce(
        () => new Promise((_, reject) => (fail = reject)),
    );
    fetchMock.mockResolvedValueOnce(Response.json({ commits: [] }));
    render();
    render('testing');
    await settle();
    assert.ok(fail);
    fail(new TypeError('Failed to fetch'));
    await settle();
    const { html } = render('testing');

    assert.match(html, /nothing close to &#x27;testing&#x27;/);
    assert.doesNotMatch(html, /role="alert"/);
});
