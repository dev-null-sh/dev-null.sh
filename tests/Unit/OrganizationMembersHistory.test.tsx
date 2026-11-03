import assert from 'node:assert/strict';
import { test, vi } from 'vite-plus/test';

type NavigationType = 'initial' | 'visit' | 'history';
type Navigation = {
    detail: {
        type: NavigationType;
        page: {
            component: string;
            props: { auth: { user: { id: string } } };
        };
    };
};

const reload = vi.fn();
let navigate: ((event: Navigation) => void) | undefined;
let current = 'log/show';

vi.doMock('@inertiajs/react', () => ({
    router: {
        on: (event: string, listener: (event: Navigation) => void) => {
            assert.equal(event, 'navigate');
            navigate = listener;

            return () => {
                navigate = undefined;
            };
        },
        reload,
    },
}));
vi.doMock('@/lib/current-page', () => ({
    currentPageComponent: () => current,
}));

const { trackOrganizationMembersHistory } = await import(
    '../../resources/js/lib/organization-members-history'
);

test('refreshes members and identity on history and bfcache restore', () => {
    reload.mockClear();
    const events = new Map<string, (event: { persisted: boolean }) => void>();
    const reloadPage = vi.fn();
    vi.stubGlobal('window', {
        location: { reload: reloadPage },
        addEventListener: (name: string, listener: (event: { persisted: boolean }) => void) =>
            events.set(name, listener),
        removeEventListener: (name: string) => events.delete(name),
    });

    try {
        const stop = trackOrganizationMembersHistory();
        const go = (type: NavigationType, component: string) =>
            navigate?.({
                detail: {
                    type,
                    page: { component, props: { auth: { user: { id: '1' } } } },
                },
            });

        go('initial', 'organization-member/index');
        go('visit', 'organization-member/index');
        go('history', 'log/show');
        assert.equal(reload.mock.calls.length, 0);

        go('history', 'organization-member/index');
        assert.equal(reload.mock.calls.length, 1);
        const options = reload.mock.calls[0][0];
        assert.deepEqual(options.only, [
            'organization', 'role', 'members', 'auth',
        ]);
        assert.equal(options.preserveScroll, true);

        options.onSuccess({ props: { auth: { user: { id: '1' } } } });
        assert.equal(reloadPage.mock.calls.length, 0);
        options.onSuccess({ props: { auth: { user: { id: '2' } } } });
        assert.equal(reloadPage.mock.calls.length, 1);

        current = 'organization-member/index';
        events.get('pageshow')?.({ persisted: false });
        assert.equal(reload.mock.calls.length, 1);
        events.get('pageshow')?.({ persisted: true });
        assert.equal(reload.mock.calls.length, 2);

        stop();
        assert.equal(events.has('pageshow'), false);
        go('history', 'organization-member/index');
        assert.equal(reload.mock.calls.length, 2);
    } finally {
        vi.unstubAllGlobals();
    }
});
