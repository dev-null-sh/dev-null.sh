import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { Commit } from '../../resources/js/types';

const listeners = new Map<string, (event: unknown) => void>();
let props: Record<string, unknown> = {};
const historyListeners = new Map<string, EventListener>();
const browserWindow = {
    location: new URL('http://localhost'),
    history: { back: vi.fn() },
    addEventListener: (name: string, callback: EventListener) =>
        historyListeners.set(name, callback),
    removeEventListener: (name: string) => historyListeners.delete(name),
};
const cancelRequests = vi.fn();
let lastVisit: { preserveState?: boolean; preserveScroll?: boolean };

function navigate(url: string, nextProps: Record<string, unknown>) {
    props = nextProps;
    browserWindow.location = new URL(url, 'http://localhost');
    listeners.get('navigate')?.({ detail: { page: { url, props } } });
}

function clientVisit(
    options: {
        url: string;
        props: (current: Record<string, unknown>) => Record<string, unknown>;
        preserveState?: boolean;
        preserveScroll?: boolean;
        onSuccess?: () => void;
    },
    pushes = true,
) {
    lastVisit = options;
    props = options.props(props);
    browserWindow.location = new URL(options.url, 'http://localhost');
    if (pushes) {
        listeners.get('navigate')?.({
            detail: { page: { url: options.url, props } },
        });
    }
    options.onSuccess?.();
}

vi.doMock('@inertiajs/react', () => ({
    router: {
        cancelAll: cancelRequests,
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
    user: { username: 'author' },
} as Commit;
let stop: VoidFunction;

beforeEach(() => {
    vi.stubGlobal('window', browserWindow);
    browserWindow.history.back.mockClear();
    cancelRequests.mockClear();
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

function historyVisit(url: string) {
    browserWindow.location = new URL(url, 'http://localhost');
    const event = new Event('popstate');
    const intercepted = vi.spyOn(event, 'stopImmediatePropagation');
    historyListeners.get('popstate')?.(event);
    return intercepted;
}

test('closes a photo through history while retaining the live background page and scroll', () => {
    openCommitPhoto(commit, 0);
    props.livePollResult = 42;
    closeCommitPhoto(props.photo as Parameters<typeof closeCommitPhoto>[0]);
    assert.equal(browserWindow.history.back.mock.calls.length, 1);

    const intercepted = historyVisit('/log/latest?since=first');

    assert.equal(intercepted.mock.calls.length, 1);
    assert.equal(props.photo, undefined);
    assert.equal(props.livePollResult, 42);
    assert.equal(lastVisit.preserveState, true);
    assert.equal(lastVisit.preserveScroll, true);
    assert.equal(backgroundVisit().url.pathname, '/log/latest');
});

test('preserves the background on browser Back and Forward, including a changed slide', () => {
    openCommitPhoto(commit, 0);
    showCommitPhoto(props.photo as Parameters<typeof showCommitPhoto>[0], 1);
    const photoUrl = window.location.href;

    historyVisit('/log/latest?since=first');
    assert.equal(props.photo, undefined);
    historyVisit(photoUrl);

    assert.deepEqual(props.photo, {
        commit,
        index: 1,
        backgroundUrl: 'http://localhost/log/latest?since=first',
    });
    assert.equal(lastVisit.preserveState, true);
    assert.equal(lastVisit.preserveScroll, true);
    assert.equal(backgroundVisit().url.pathname, '/log/latest');
});

test('leaves history to other pages and an old photo session to Inertia', () => {
    openCommitPhoto(commit, 0);
    assert.equal(historyVisit('/@another').mock.calls.length, 0);
    navigate('/@another', {});

    assert.equal(historyVisit('/log/latest?since=first').mock.calls.length, 0);
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

test('restores a directly loaded photo after closing a reply photo', () => {
    const original = { commit, index: 1 };
    navigate('/@author/commits/image-post/photos/2', { photo: original });
    const reply = { ...commit, id: 'reply-image' };
    openCommitPhoto(reply, 0);
    closeCommitPhoto(props.photo as Parameters<typeof closeCommitPhoto>[0]);

    assert.equal(
        historyVisit('/@author/commits/image-post/photos/2').mock.calls.length,
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

for (const outcome of [
    'pending',
    'cancelled',
    'httpException',
    'networkError',
]) {
    test(`retains photo history after a ${outcome} full visit`, () => {
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
            historyVisit('/log/latest?since=first').mock.calls.length,
            1,
        );
        assert.equal(props.photo, undefined);
        assert.equal(props.livePollResult, 42);
        assert.equal(lastVisit.preserveState, true);
        assert.deepEqual(cancelRequests.mock.calls, [[{ prefetch: false }]]);
    });
}

for (const outcome of ['success', 'error']) {
    test(`releases photo history after a full visit commits with ${outcome}, even at the same URL`, () => {
        openCommitPhoto(commit, 0);
        const visit = backgroundVisit({ async: false, id: 'full-visit' });
        listeners.get('start')?.({ detail: { visit } });
        navigate('/log/latest?since=first', {});
        listeners.get(outcome)?.({
            detail: { page: { props }, visitId: visit.id },
        });

        assert.equal(
            historyVisit('/log/latest?since=first').mock.calls.length,
            0,
        );
    });
}

for (const outcome of ['success', 'error']) {
    test(`uses the committed photo page for polling after a full replacement with ${outcome}`, () => {
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
            historyVisit('/log/latest?since=first').mock.calls.length,
            0,
        );
    });
}

test('keeps photo history when a background poll commits during a full visit', () => {
    openCommitPhoto(commit, 0);
    const visit = backgroundVisit({ async: false, id: 'full-visit' });
    listeners.get('start')?.({ detail: { visit } });
    listeners.get('success')?.({
        detail: { page: { props }, visitId: 'poll' },
    });

    assert.equal(historyVisit('/log/latest?since=first').mock.calls.length, 1);
});

test('removes the history listener when photo tracking stops', () => {
    openCommitPhoto(commit, 0);
    stop();

    assert.equal(historyListeners.has('popstate'), false);
    assert.equal(historyVisit('/log/latest?since=first').mock.calls.length, 0);
});
