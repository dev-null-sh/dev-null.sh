import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { commitMutationOptions } from '../../resources/js/lib/commit-mutation-options';
import type { Commit } from '../../resources/js/types';

const listeners = new Map<string, (event: unknown) => void>();
let props: Record<string, unknown> = {};
const historyListeners = new Map<string, EventListener>();
type HistoryState = {
    page: { url: string; props: Record<string, unknown>; version?: string };
    [key: string]: unknown;
};
const historyStates = new Map<string, HistoryState>();
const browserWindow = {
    location: new URL('http://localhost'),
    dispatchEvent: vi.fn(),
    history: {
        back: vi.fn(),
        state: null as HistoryState | null,
        replaceState: (
            data: HistoryState,
            _unused: string,
            url?: string | URL | null,
        ) => {
            if (url) {
                browserWindow.location = new URL(url, browserWindow.location);
            }
            browserWindow.history.state = structuredClone(data);
            historyStates.set(
                browserWindow.location.href,
                structuredClone(data),
            );
        },
    },
    addEventListener: (name: string, callback: EventListener) =>
        historyListeners.set(name, callback),
    removeEventListener: (name: string) => historyListeners.delete(name),
};
const replaceHistoryState = browserWindow.history.replaceState;
const cancelRequests = vi.fn();
const decryptHistory = vi.fn(async () =>
    structuredClone(browserWindow.history.state?.page),
);
let lastVisit: { preserveState?: boolean; preserveScroll?: boolean };

function navigate(url: string, nextProps: Record<string, unknown>) {
    props = nextProps;
    browserWindow.location = new URL(url, 'http://localhost');
    browserWindow.history.state = {
        page: {
            url: browserWindow.location.href,
            props: structuredClone(props),
        },
    };
    historyStates.set(
        browserWindow.location.href,
        structuredClone(browserWindow.history.state),
    );
    listeners.get('navigate')?.({ detail: { page: { url, props } } });
}

function clientVisit(
    options: {
        url: string;
        props: (current: Record<string, unknown>) => Record<string, unknown>;
        preserveState?: boolean;
        preserveScroll?: boolean;
        onSuccess?: () => void;
        onFinish?: () => void;
    },
    pushes = true,
) {
    lastVisit = options;
    props = options.props(props);
    browserWindow.location = new URL(options.url, 'http://localhost');
    const state = {
        page: {
            url: browserWindow.location.href,
            props: structuredClone(props),
        },
    };
    if (pushes) {
        browserWindow.history.state = state;
        historyStates.set(browserWindow.location.href, structuredClone(state));
        listeners.get('navigate')?.({
            detail: { page: { url: options.url, props } },
        });
    } else {
        browserWindow.history.replaceState(state, '', options.url);
    }
    options.onSuccess?.();
    options.onFinish?.();
}

vi.doMock('@inertiajs/react', () => ({
    router: {
        cancelAll: cancelRequests,
        decryptHistory,
        on: (name: string, callback: (event: unknown) => void) => {
            listeners.set(name, callback);
            return () => listeners.delete(name);
        },
        push: (options: Parameters<typeof clientVisit>[0]) =>
            clientVisit(options),
        replace: (options: Parameters<typeof clientVisit>[0]) =>
            clientVisit(options, false),
    },
}));

const {
    closeCommitPhoto,
    openCommitPhoto,
    showCommitPhoto,
    trackCommitPhotoPage,
} = await import('../../resources/js/lib/commit-photo');

const commit = {
    id: 'image-post',
    user: { handle: 'author' },
} as Commit;
let stop: VoidFunction;

beforeEach(() => {
    vi.stubGlobal('window', browserWindow);
    browserWindow.history.back.mockClear();
    cancelRequests.mockClear();
    decryptHistory
        .mockReset()
        .mockImplementation(async () =>
            structuredClone(browserWindow.history.state?.page),
        );
    browserWindow.dispatchEvent.mockClear();
    historyStates.clear();
    browserWindow.history.state = null;
    stop = trackCommitPhotoPage();
    navigate('/log/latest?since=first', {});
});
afterEach(() => {
    stop();
    vi.unstubAllGlobals();
});

function backgroundVisit(overrides = {}) {
    const visit = {
        id: 'poll-visit',
        preserveState: false,
        url: new URL(window.location.href),
        method: 'get',
        async: true,
        preserveUrl: false,
        only: ['auth.unreadNotificationsCount'],
        ...overrides,
    };
    listeners.get('before')?.({ detail: { visit } });
    return visit;
}

for (const source of [
    '/log/latest?since=first',
    '/@author/commits/image-post',
]) {
    for (const only of [
        ['newCommitsCount'],
        ['auth.unreadNotificationsCount', 'auth.unreadMessagesCount'],
    ]) {
        test(`keeps ${only.join(', ')} polling on ${source} while its photo is open`, () => {
            navigate(source, {});
            openCommitPhoto(commit, 0);
            const photoUrl = window.location.href;

            const visit = backgroundVisit({ only });

            assert.equal(visit.url.href, `http://localhost${source}`);
            assert.equal(visit.preserveUrl, true);
            assert.equal(window.location.href, photoUrl);
        });
    }
}

test('preserves source filters and replaces polling query values without losing repeated values', () => {
    navigate('/log/trending?since=first&filter=mine', {});
    openCommitPhoto(commit, 0);

    const visit = backgroundVisit({
        url: new URL(
            `${window.location.href}?since=second&ids[]=one&ids[]=two`,
        ),
    });

    assert.equal(visit.url.pathname, '/log/trending');
    assert.equal(visit.url.searchParams.get('filter'), 'mine');
    assert.deepEqual(visit.url.searchParams.getAll('since'), ['second']);
    assert.deepEqual(visit.url.searchParams.getAll('ids[]'), ['one', 'two']);
});

test('keeps the source across photo navigation and browser history restoration', () => {
    openCommitPhoto(commit, 0);
    const restoredProps = structuredClone(props);
    const firstPhotoUrl = window.location.href;
    showCommitPhoto(props.photo as Parameters<typeof showCommitPhoto>[0], 1);
    openCommitPhoto(commit, 0);
    assert.equal(backgroundVisit().url.pathname, '/log/latest');

    navigate('/@another', {});
    navigate(firstPhotoUrl, restoredProps);

    assert.equal(backgroundVisit().url.pathname, '/log/latest');
    navigate('/@another', {});
    assert.equal(backgroundVisit().url.pathname, '/@another');
});

test('leaves direct photo-page reloads on the photo route', () => {
    navigate('/@author/commits/image-post/photos/1', {
        photo: { commit, index: 0 },
    });

    assert.equal(backgroundVisit().url.href, window.location.href);
    assert.equal(backgroundVisit().preserveUrl, false);
});

for (const overrides of [
    { method: 'post' },
    { async: false },
    { url: new URL('http://localhost/@other') },
    { url: new URL('https://example.com/@author/commits/image-post/photos/1') },
]) {
    test(`leaves unrelated navigation and mutations untouched: ${JSON.stringify(overrides)}`, () => {
        openCommitPhoto(commit, 0);
        const originalUrl = overrides.url?.href ?? window.location.href;

        const visit = backgroundVisit(overrides);

        assert.equal(visit.url.href, originalUrl);
        assert.equal(visit.preserveUrl, false);
    });
}

async function historyVisit(url: string, state?: HistoryState | null) {
    browserWindow.location = new URL(url, 'http://localhost');
    const target =
        state === undefined
            ? (historyStates.get(browserWindow.location.href) ?? null)
            : state;
    browserWindow.history.state = structuredClone(target);
    const event = new Event('popstate');
    Object.defineProperty(event, 'state', { value: target });
    const intercepted = vi.spyOn(event, 'stopImmediatePropagation');
    historyListeners.get('popstate')?.(event);
    await new Promise(setImmediate);
    return intercepted;
}

test('closes a photo through history while retaining the live background page and scroll', async () => {
    openCommitPhoto(commit, 0);
    props.livePollResult = 42;
    closeCommitPhoto(props.photo as Parameters<typeof closeCommitPhoto>[0]);
    assert.equal(browserWindow.history.back.mock.calls.length, 1);

    const intercepted = await historyVisit('/log/latest?since=first');

    assert.equal(intercepted.mock.calls.length, 1);
    assert.equal(props.photo, undefined);
    assert.equal(props.livePollResult, 42);
    assert.equal(lastVisit.preserveState, true);
    assert.equal(lastVisit.preserveScroll, true);
    assert.equal(backgroundVisit().url.pathname, '/log/latest');
});

test('preserves the background on browser Back and Forward, including a changed slide', async () => {
    openCommitPhoto(commit, 0);
    showCommitPhoto(props.photo as Parameters<typeof showCommitPhoto>[0], 1);
    const photoUrl = window.location.href;

    await historyVisit('/log/latest?since=first');
    assert.equal(props.photo, undefined);
    await historyVisit(photoUrl);

    assert.deepEqual(props.photo, {
        commit,
        index: 1,
        backgroundUrl: 'http://localhost/log/latest?since=first',
    });
    assert.equal(lastVisit.preserveState, true);
    assert.equal(lastVisit.preserveScroll, true);
    assert.equal(backgroundVisit().url.pathname, '/log/latest');
});

test('leaves history to other pages and an old photo session to Inertia', async () => {
    openCommitPhoto(commit, 0);
    assert.equal((await historyVisit('/@another')).mock.calls.length, 0);
    navigate('/@another', {});

    assert.equal(
        (await historyVisit('/log/latest?since=first')).mock.calls.length,
        0,
    );
});

test('closes a directly loaded photo on its commit without going back', () => {
    navigate('/@author/commits/image-post/photos/1', {
        photo: { commit, index: 0 },
    });
    closeCommitPhoto(props.photo as Parameters<typeof closeCommitPhoto>[0]);

    assert.equal(browserWindow.history.back.mock.calls.length, 0);
    assert.equal(window.location.pathname, '/@author/commits/image-post');
    assert.equal(props.photo, undefined);
    assert.equal(lastVisit.preserveState, true);
});

test('restores a directly loaded photo after closing a reply photo', async () => {
    const original = { commit, index: 1 };
    navigate('/@author/commits/image-post/photos/2', { photo: original });
    const reply = { ...commit, id: 'reply-image' };
    openCommitPhoto(reply, 0);
    closeCommitPhoto(props.photo as Parameters<typeof closeCommitPhoto>[0]);

    assert.equal(
        (await historyVisit('/@author/commits/image-post/photos/2')).mock.calls
            .length,
        1,
    );
    assert.deepEqual(props.photo, original);
    assert.equal(
        backgroundVisit().url.pathname,
        '/@author/commits/image-post/photos/2',
    );
    closeCommitPhoto(props.photo as Parameters<typeof closeCommitPhoto>[0]);
    assert.equal(window.location.pathname, '/@author/commits/image-post');
    assert.equal(browserWindow.history.back.mock.calls.length, 1);
});

test('retains a changed direct-photo slide when returning from a nested photo through Forward and Close', async () => {
    navigate('/@author/commits/image-post/photos/1', {
        photo: { commit, index: 0 },
    });
    const reply = { ...commit, id: 'reply-image' };
    openCommitPhoto(reply, 0);
    const replyUrl = window.location.href;
    await historyVisit('/@author/commits/image-post/photos/1');
    showCommitPhoto(props.photo as Parameters<typeof showCommitPhoto>[0], 1);
    props.livePollResult = 42;

    assert.equal((await historyVisit(replyUrl)).mock.calls.length, 1);
    closeCommitPhoto(props.photo as Parameters<typeof closeCommitPhoto>[0]);
    assert.equal(
        (await historyVisit('/@author/commits/image-post/photos/2')).mock.calls
            .length,
        1,
    );
    assert.deepEqual(props.photo, { commit, index: 1 });
    assert.equal(props.livePollResult, 42);
    assert.equal(lastVisit.preserveState, true);
});

test('retains the commit replacing a restored direct viewer when returning from a nested photo', async () => {
    navigate('/@author/commits/image-post/photos/1', {
        photo: { commit, index: 0 },
    });
    openCommitPhoto({ ...commit, id: 'reply-image' }, 0);
    const replyUrl = window.location.href;
    await historyVisit('/@author/commits/image-post/photos/1');
    closeCommitPhoto(props.photo as Parameters<typeof closeCommitPhoto>[0]);
    assert.equal(window.location.pathname, '/@author/commits/image-post');

    await historyVisit(replyUrl);
    closeCommitPhoto(props.photo as Parameters<typeof closeCommitPhoto>[0]);
    assert.equal(
        (await historyVisit('/@author/commits/image-post')).mock.calls.length,
        1,
    );
    assert.equal(props.photo, undefined);
    assert.equal(lastVisit.preserveState, true);
});

test('leaves standalone direct-gallery history to Inertia before a nested photo session exists', async () => {
    navigate('/@author/commits/image-post/photos/1', {
        photo: { commit, index: 0 },
    });
    showCommitPhoto(props.photo as Parameters<typeof showCommitPhoto>[0], 1);

    assert.equal(
        (await historyVisit('/@author/commits/image-post/photos/2')).mock.calls
            .length,
        0,
    );
});

for (const outcome of [
    'pending',
    'cancelled',
    'httpException',
    'networkError',
]) {
    test(`retains photo history after a ${outcome} full visit`, async () => {
        openCommitPhoto(commit, 0);
        props.livePollResult = 42;
        const visit = backgroundVisit({ async: false, id: 'full-visit' });
        listeners.get('start')?.({ detail: { visit } });
        if (outcome !== 'pending') {
            if (outcome !== 'cancelled') {
                listeners.get(outcome)?.({ detail: {} });
            }
            listeners.get('finish')?.({ detail: { visit } });
        }

        assert.equal(
            (await historyVisit('/log/latest?since=first')).mock.calls.length,
            1,
        );
        assert.equal(props.photo, undefined);
        assert.equal(props.livePollResult, 42);
        assert.equal(lastVisit.preserveState, true);
        assert.deepEqual(cancelRequests.mock.calls, [[{ prefetch: false }]]);
    });
}

for (const outcome of ['success', 'error']) {
    test(`releases photo history after a full visit commits with ${outcome}, even at the same URL`, async () => {
        openCommitPhoto(commit, 0);
        const visit = backgroundVisit({ async: false, id: 'full-visit' });
        listeners.get('start')?.({ detail: { visit } });
        navigate('/log/latest?since=first', {});
        listeners.get(outcome)?.({
            detail: { page: { props }, visitId: visit.id },
        });

        assert.equal(
            (await historyVisit('/log/latest?since=first')).mock.calls.length,
            0,
        );
    });
}

for (const outcome of ['success', 'error']) {
    test(`uses the committed photo page for polling after a full replacement with ${outcome}`, async () => {
        openCommitPhoto(commit, 0);
        const photoUrl = window.location.href;
        const visit = backgroundVisit({ async: false, id: 'full-visit' });
        listeners.get('start')?.({ detail: { visit } });
        props = { photo: { commit, index: 0 } };
        listeners.get(outcome)?.({
            detail: { page: { props }, visitId: visit.id },
        });

        assert.equal(backgroundVisit().url.href, photoUrl);
        assert.equal(backgroundVisit().preserveUrl, false);
        assert.equal(
            (await historyVisit('/log/latest?since=first')).mock.calls.length,
            0,
        );
    });
}

for (const outcome of ['success', 'error']) {
    test(`keeps photo history after a preserved-state commit mutation with ${outcome}`, async () => {
        openCommitPhoto(commit, 0);
        const visit = backgroundVisit({
            ...commitMutationOptions('commit/show'),
            async: false,
            method: 'delete',
            id: 'commit-deletion',
        });
        listeners.get('start')?.({ detail: { visit } });
        props.liveReplyCount = 2;
        listeners.get(outcome)?.({
            detail: { page: { props }, visitId: visit.id },
        });

        closeCommitPhoto(props.photo as Parameters<typeof closeCommitPhoto>[0]);
        assert.equal(
            (await historyVisit('/log/latest?since=first')).mock.calls.length,
            1,
        );
        assert.equal(props.photo, undefined);
        assert.equal(props.liveReplyCount, 2);
        assert.equal(lastVisit.preserveState, true);
    });

    test(`keeps Forward photo history after a preserved-state background mutation with ${outcome}`, async () => {
        openCommitPhoto(commit, 0);
        const photoUrl = window.location.href;
        await historyVisit('/log/latest?since=first');
        const visit = backgroundVisit({
            ...commitMutationOptions('log/show'),
            async: false,
            method: 'delete',
            id: 'commit-deletion',
        });
        listeners.get('start')?.({ detail: { visit } });
        listeners.get(outcome)?.({
            detail: { page: { props }, visitId: visit.id },
        });

        assert.equal((await historyVisit(photoUrl)).mock.calls.length, 1);
        assert.equal((props.photo as { commit: Commit }).commit.id, commit.id);
        assert.equal(lastVisit.preserveState, true);
    });
}

test('releases photo history when a preserved-state response changes the background session', async () => {
    openCommitPhoto(commit, 0);
    const visit = backgroundVisit({
        async: false,
        preserveState: true,
        id: 'new-session',
    });
    listeners.get('start')?.({ detail: { visit } });
    props = {
        photo: { commit, index: 0, backgroundUrl: 'http://localhost/@another' },
    };
    listeners.get('success')?.({
        detail: { page: { props }, visitId: visit.id },
    });

    assert.equal(
        (await historyVisit('/log/latest?since=first')).mock.calls.length,
        0,
    );
});

test('keeps photo history when a background poll commits during a full visit', async () => {
    openCommitPhoto(commit, 0);
    const visit = backgroundVisit({ async: false, id: 'full-visit' });
    listeners.get('start')?.({ detail: { visit } });
    listeners.get('success')?.({
        detail: { page: { props }, visitId: 'poll' },
    });

    assert.equal(
        (await historyVisit('/log/latest?since=first')).mock.calls.length,
        1,
    );
});

test('leaves an older occurrence of the same photo URL to Inertia after a new background session', async () => {
    openCommitPhoto(commit, 0);
    const photoUrl = window.location.href;
    const oldPhotoState = structuredClone(browserWindow.history.state);
    navigate('/@author', {});
    openCommitPhoto(commit, 0);
    await historyVisit('/@author');

    assert.equal(
        (await historyVisit(photoUrl, oldPhotoState)).mock.calls.length,
        0,
    );
});

test('restores distinct entries sharing a photo URL within the same session', async () => {
    const sourceUrl = 'http://localhost/@author/commits/image-post/photos/1';
    navigate(sourceUrl, { photo: { commit, index: 0 } });
    openCommitPhoto({ ...commit, id: 'reply-image' }, 0);
    const sourceState = structuredClone(historyStates.get(sourceUrl));
    const replyUrl = window.location.href;
    openCommitPhoto(commit, 0);
    await historyVisit(replyUrl);

    assert.equal(
        (await historyVisit(sourceUrl, sourceState)).mock.calls.length,
        1,
    );
    assert.deepEqual(props.photo, { commit, index: 0 });
    closeCommitPhoto(props.photo as Parameters<typeof closeCommitPhoto>[0]);
    assert.equal(window.location.pathname, '/@author/commits/image-post');
});

test('retains a photo entry through framework history replacements for scrolling or remembered state', async () => {
    openCommitPhoto(commit, 0);
    const photoUrl = window.location.href;
    browserWindow.history.replaceState(
        { page: { url: photoUrl, props: structuredClone(props) } },
        '',
    );
    const replacedState = structuredClone(browserWindow.history.state);
    await historyVisit('/log/latest?since=first');

    assert.equal(
        (await historyVisit(photoUrl, replacedState)).mock.calls.length,
        1,
    );
    assert.equal((props.photo as { commit: Commit }).commit.id, commit.id);
});

test('leaves prior-document photo history to Inertia after reloading and closing a photo', async () => {
    openCommitPhoto(commit, 0);
    const sourceUrl = 'http://localhost/log/latest?since=first';
    const source = structuredClone(historyStates.get(sourceUrl));
    stop();
    stop = trackCommitPhotoPage();
    props = { photo: { commit, index: 0 } };
    listeners.get('navigate')?.({
        detail: { page: { component: 'commit/show', props } },
    });
    closeCommitPhoto(props.photo as Parameters<typeof closeCommitPhoto>[0]);
    assert.equal(window.location.pathname, '/@author/commits/image-post');

    assert.equal((await historyVisit(sourceUrl, source)).mock.calls.length, 0);
});

test('falls back to native history when a photo entry cannot be decrypted', async () => {
    openCommitPhoto(commit, 0);
    const visit = lastVisit;
    decryptHistory.mockRejectedValueOnce(new Error('History key unavailable'));

    assert.equal(
        (await historyVisit('/log/latest?since=first')).mock.calls.length,
        1,
    );
    assert.equal(browserWindow.dispatchEvent.mock.calls.length, 1);
    assert.equal(lastVisit, visit);
});

test('falls back to native history for a photo entry from a different app version', async () => {
    openCommitPhoto(commit, 0);
    const source = structuredClone(
        historyStates.get('http://localhost/log/latest?since=first'),
    );
    assert.ok(source);
    source.page.version = 'old-version';

    await historyVisit('/log/latest?since=first', source);

    assert.equal(browserWindow.dispatchEvent.mock.calls.length, 1);
});

test('ignores a decoded photo entry superseded by a second Back', async () => {
    openCommitPhoto(commit, 0);
    const firstUrl = window.location.href;
    const first = structuredClone(browserWindow.history.state);
    openCommitPhoto({ ...commit, id: 'reply-image' }, 0);
    let decode!: (page: NonNullable<HistoryState>['page']) => void;
    decryptHistory.mockImplementationOnce(
        () =>
            new Promise((resolve) => {
                decode = resolve;
            }),
    );

    const backToPhoto = historyVisit(firstUrl, first);
    await Promise.resolve();
    const backToSource = historyVisit('/log/latest?since=first');
    assert.ok(first);
    decode(first.page);
    await Promise.all([backToPhoto, backToSource]);

    assert.equal(props.photo, undefined);
    assert.equal(window.location.pathname, '/log/latest');
    assert.equal(lastVisit.preserveState, true);
});

test('removes the history listener when photo tracking stops', async () => {
    openCommitPhoto(commit, 0);
    stop();

    assert.equal(historyListeners.has('popstate'), false);
    assert.equal(browserWindow.history.replaceState, replaceHistoryState);
    assert.equal(
        (await historyVisit('/log/latest?since=first')).mock.calls.length,
        0,
    );
});
