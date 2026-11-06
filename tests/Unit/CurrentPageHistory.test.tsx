import assert from 'node:assert/strict';
import { test, vi } from 'vite-plus/test';

type NavigationType = 'initial' | 'visit' | 'history';
type NavigationEvent = {
    detail: {
        type: NavigationType;
        page: { component: string; url: string };
    };
};

const visit = vi.fn();
let navigate: ((event: NavigationEvent) => void) | undefined;

vi.doMock('@inertiajs/react', () => ({
    router: {
        on: (event: string, listener: (event: NavigationEvent) => void) => {
            assert.equal(event, 'navigate');
            navigate = listener;

            return () => {
                navigate = undefined;
            };
        },
        visit,
    },
}));

const { currentPageComponent, trackCurrentPage } = await import(
    '../../resources/js/lib/current-page'
);

test('refreshes only non-live props after history and bfcache restores', () => {
    visit.mockClear();
    const reloadDocument = vi.fn();
    let pageShow: ((event: { persisted: boolean }) => void) | undefined;
    vi.stubGlobal('window', {
        location: { reload: reloadDocument },
        addEventListener: (
            name: string,
            listener: (event: { persisted: boolean }) => void,
        ) => {
            assert.equal(name, 'pageshow');
            pageShow = listener;
        },
        removeEventListener: (
            name: string,
            listener: (event: { persisted: boolean }) => void,
        ) => {
            assert.equal(name, 'pageshow');
            assert.equal(pageShow, listener);
            pageShow = undefined;
        },
    });

    try {
        const stop = trackCurrentPage();
        const dispatch = (
            type: NavigationType,
            component: string,
            url = `/${component}`,
        ) =>
            navigate?.({
                detail: { type, page: { component, url } },
            });

        pageShow?.({ persisted: true });
        dispatch('initial', 'device/index');
        dispatch('visit', 'safety/edit');
        dispatch('history', 'log/show');
        assert.equal(currentPageComponent(), 'log/show');
        assert.equal(visit.mock.calls.length, 0);

        // #1458 already refreshes live props, and moderation has its own catch-up.
        [
            'device/index',
            'safety/edit',
            'organization-member/index',
            'moderation/show',
        ].forEach((name) => {
            dispatch('history', name);
            pageShow?.({ persisted: true });
        });
        assert.equal(visit.mock.calls.length, 0);

        const expected = [
            { only: ['features'], preserveScroll: true },
            { only: ['recipients'], preserveScroll: true },
            { only: ['commits'], reset: ['commits'], preserveScroll: false },
        ];
        const components = [
            'operations/features',
            'operations/emails',
            'starred/index',
        ];

        components.forEach((name) => dispatch('history', name));
        assert.deepEqual(
            visit.mock.calls.map(([url, options]) => ({
                url,
                only: options.only,
                ...(options.reset === undefined ? {} : { reset: options.reset }),
                preserveScroll: options.preserveScroll,
                method: options.method,
                async: options.async,
                preserveState: options.preserveState,
                replace: options.replace,
                onHttpException: typeof options.onHttpException,
            })),
            expected.map((options, index) => ({
                url: `/${components[index]}`,
                ...options,
                method: 'get',
                async: true,
                preserveState: true,
                replace: true,
                onHttpException: 'function',
            })),
        );

        pageShow?.({ persisted: false });
        assert.equal(visit.mock.calls.length, expected.length);

        // A bfcache restore doesn't cause an Inertia history navigation.
        dispatch('visit', 'starred/index');
        pageShow?.({ persisted: true });
        assert.equal(visit.mock.calls.length, expected.length + 1);
        assert.equal(visit.mock.calls.at(-1)?.[0], '/starred/index');
        assert.deepEqual(visit.mock.calls.at(-1)?.[1].reset, ['commits']);

        const onHttpException = visit.mock.calls[0][1].onHttpException;
        assert.equal(onHttpException?.({ status: 403 } as never), false);
        assert.equal(onHttpException?.({ status: 404 } as never), false);
        assert.equal(onHttpException?.({ status: 503 } as never), undefined);
        assert.equal(reloadDocument.mock.calls.length, 2);

        // When a photo is open over Starred, this must be an async GET to the
        // visible photo URL: trackCommitPhotoPage redirects it to the starred
        // background and preserves the photo URL and overlay.
        const photoUrl = '/@author/commits/123/photos/1';
        dispatch('history', 'starred/index', photoUrl);
        assert.equal(visit.mock.lastCall?.[0], photoUrl);
        assert.equal(visit.mock.lastCall?.[1].async, true);
        assert.deepEqual(visit.mock.lastCall?.[1].only, ['commits']);
        pageShow?.({ persisted: true });
        assert.equal(visit.mock.lastCall?.[0], photoUrl);
        assert.equal(visit.mock.lastCall?.[1].async, true);

        const beforeStop = visit.mock.calls.length;
        stop();
        dispatch('history', 'starred/index');
        pageShow?.({ persisted: true });
        assert.equal(visit.mock.calls.length, beforeStop);
    } finally {
        vi.unstubAllGlobals();
    }
});
