import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { Commit } from '../../resources/js/types';

const listeners = new Map<string, (event: unknown) => void>();
let props: Record<string, unknown> = {};

function navigate(url: string, nextProps: Record<string, unknown>) {
    props = nextProps;
    vi.stubGlobal('window', {
        location: new URL(url, 'http://localhost'),
        history: { back: vi.fn() },
    });
    listeners.get('navigate')?.({ detail: { page: { url, props } } });
}

function clientVisit(options: {
    url: string;
    props: (current: Record<string, unknown>) => Record<string, unknown>;
}) {
    navigate(options.url, options.props(props));
}

vi.doMock('@inertiajs/react', () => ({
    router: {
        on: (name: string, callback: (event: unknown) => void) => {
            listeners.set(name, callback);
            return () => listeners.delete(name);
        },
        push: clientVisit,
        replace: clientVisit,
    },
}));

const { openCommitPhoto, showCommitPhoto, trackCommitPhotoPage } =
    await import('../../resources/js/lib/commit-photo');

const commit = {
    id: 'image-post',
    user: { username: 'author' },
} as Commit;
let stop: VoidFunction;

beforeEach(() => {
    stop = trackCommitPhotoPage();
    navigate('/log/latest?since=first', {});
});
afterEach(() => {
    stop();
    vi.unstubAllGlobals();
});

function backgroundVisit(overrides = {}) {
    const visit = {
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
