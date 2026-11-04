import assert from 'node:assert/strict';
import { test, vi } from 'vite-plus/test';

type NavigationType = 'initial' | 'visit' | 'history';
type Navigation = {
    detail: {
        type: NavigationType;
        page: { component: string; url: string; props: { photo?: { backgroundUrl: string } } };
    };
};

const visit = vi.fn();
const cancelAll = vi.fn();
let navigate: ((event: Navigation) => void) | undefined;

vi.doMock('@inertiajs/react', () => ({
    router: {
        on: (event: string, listener: (event: Navigation) => void) => {
            assert.equal(event, 'navigate');
            navigate = listener;

            return () => {
                navigate = undefined;
            };
        },
        visit,
        cancelAll,
    },
}));

const { trackStarredCommitsHistory } = await import(
    '../../resources/js/lib/starred-commits-history'
);

test('refreshes starred commits after history listeners have completed', async () => {
    visit.mockClear();
    cancelAll.mockClear();
    const stop = trackStarredCommitsHistory();
    const go = (
        type: NavigationType,
        component: string,
        photo?: { backgroundUrl: string },
        url = '/starred',
    ) =>
        navigate?.({ detail: { type, page: { component, url, props: { photo } } } });

    go('initial', 'starred/index');
    go('visit', 'starred/index');
    go('history', 'log/show');
    assert.equal(visit.mock.calls.length, 0);

    go('history', 'starred/index');
    assert.equal(visit.mock.calls.length, 0);
    await Promise.resolve();
    assert.equal(visit.mock.calls.length, 1);
    assert.equal(cancelAll.mock.calls.length, 1);
    assert.deepEqual(cancelAll.mock.calls[0][0], {
        async: true,
        prefetch: false,
        sync: false,
    });
    assert.equal(visit.mock.calls[0][0], '/starred');
    assert.deepEqual(visit.mock.calls[0][1], {
        only: ['commits'],
        reset: ['commits'],
        preserveState: true,
        preserveScroll: false,
        preserveUrl: false,
    });

    go('history', 'starred/index', { backgroundUrl: '/starred' }, '/post/photo/1');
    assert.equal(visit.mock.calls.length, 1);
    await Promise.resolve();
    assert.equal(cancelAll.mock.calls.length, 2);
    assert.equal(visit.mock.calls[1][0], '/starred');
    assert.deepEqual(visit.mock.calls[1][1], {
        only: ['commits'],
        reset: ['commits'],
        preserveState: true,
        preserveScroll: false,
        preserveUrl: true,
    });

    stop();
    go('history', 'starred/index');
    assert.equal(visit.mock.calls.length, 2);
});
