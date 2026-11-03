import assert from 'node:assert/strict';
import { test, vi } from 'vite-plus/test';

type NavigationType = 'initial' | 'visit' | 'history';
type Navigation = {
    detail: {
        type: NavigationType;
        page: { component: string };
    };
};

const reload = vi.fn();
let current = 'log/show';

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
        reload,
    },
}));

vi.doMock('@/lib/current-page', () => ({
    currentPageComponent: () => current,
}));

const { trackOrganizationMembersHistory } = await import(
    '../../resources/js/lib/organization-members-history'
);

test('refreshes organization members and roles only on browser history restoration', () => {
    reload.mockClear();
    const events = new Map<string, (event: { persisted: boolean }) => void>();
    vi.stubGlobal('window', {
        addEventListener: (name: string, listener: (event: { persisted: boolean }) => void) =>
            events.set(name, listener),
        removeEventListener: (name: string) => events.delete(name),
    });
    const stop = trackOrganizationMembersHistory();
    const go = (type: NavigationType, component: string) =>
        navigate?.({ detail: { type, page: { component } } });

    go('initial', 'organization-member/index');
    go('visit', 'organization-member/index');
    go('history', 'log/show');
    assert.equal(reload.mock.calls.length, 0);

    go('history', 'organization-member/index');
    assert.equal(reload.mock.calls.length, 1);
    assert.deepEqual(reload.mock.calls[0][0], {
        only: ['organization', 'role', 'members'],
        preserveScroll: true,
    });

    current = 'organization-member/index';
    events.get('pageshow')?.({ persisted: false });
    assert.equal(reload.mock.calls.length, 1);
    events.get('pageshow')?.({ persisted: true });
    assert.equal(reload.mock.calls.length, 2);

    stop();
    assert.equal(events.has('pageshow'), false);
    go('history', 'organization-member/index');
    assert.equal(reload.mock.calls.length, 2);
    vi.unstubAllGlobals();
});
