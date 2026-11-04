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

test('refreshes only stale props when restoring a cached page', () => {
    visit.mockClear();
    const stop = trackCurrentPage();
    const dispatch = (type: NavigationType, component: string) =>
        navigate?.({
            detail: { type, page: { component, url: `/${component}` } },
        });

    dispatch('initial', 'device/index');
    dispatch('visit', 'safety/edit');
    dispatch('history', 'log/show');
    assert.equal(currentPageComponent(), 'log/show');
    assert.equal(visit.mock.calls.length, 0);

    const expected = [
        { only: ['devices'], preserveScroll: true },
        { only: ['muted', 'blocked'], preserveScroll: true },
        {
            only: ['organization', 'role', 'members'],
            preserveScroll: true,
        },
        { only: ['features'], preserveScroll: true },
        { only: ['recipients'], preserveScroll: true },
        { only: ['commits'], reset: ['commits'], preserveScroll: false },
        {
            only: ['commits', 'hasUnmoderatedCommits'],
            reset: ['commits'],
            preserveScroll: false,
        },
    ];
    const components = [
        'device/index',
        'safety/edit',
        'organization-member/index',
        'operations/features',
        'operations/emails',
        'starred/index',
        'moderation/show',
    ];

    components.forEach((component) => dispatch('history', component));
    assert.deepEqual(
        visit.mock.calls.map(([url, options]) => ({
            url,
            only: options.only,
            ...(options.reset === undefined ? {} : { reset: options.reset }),
            preserveScroll: options.preserveScroll,
            method: options.method,
            preserveState: options.preserveState,
            replace: options.replace,
            onHttpException: typeof options.onHttpException,
        })),
        expected.map((options, index) => ({
            url: `/${components[index]}`,
            ...options,
            method: 'get',
            preserveState: true,
            replace: true,
            onHttpException: 'function',
        })),
    );

    const hadWindow = 'window' in globalThis;
    const oldWindow = globalThis.window;
    const reloadDocument = vi.fn();
    Object.defineProperty(globalThis, 'window', {
        configurable: true,
        value: { location: { reload: reloadDocument } },
    });
    const onHttpException = visit.mock.calls[0][1].onHttpException;
    assert.equal(onHttpException?.({ status: 403 } as never), false);
    assert.equal(onHttpException?.({ status: 404 } as never), false);
    assert.equal(onHttpException?.({ status: 503 } as never), undefined);
    assert.equal(reloadDocument.mock.calls.length, 2);
    if (hadWindow) {
        Object.defineProperty(globalThis, 'window', {
            configurable: true,
            value: oldWindow,
        });
    } else {
        Reflect.deleteProperty(globalThis, 'window');
    }

    stop();
    dispatch('history', 'device/index');
    assert.equal(visit.mock.calls.length, expected.length);
});
