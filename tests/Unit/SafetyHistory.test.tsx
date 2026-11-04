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

const { trackSafetyHistory } = await import(
    '../../resources/js/lib/safety-history'
);

test('refreshes safety mute and block lists only on browser history restoration', () => {
    reload.mockClear();
    const stop = trackSafetyHistory();
    const go = (type: NavigationType, component: string) =>
        navigate?.({ detail: { type, page: { component } } });

    go('initial', 'safety/edit');
    go('visit', 'safety/edit');
    go('history', 'log/show');
    assert.equal(reload.mock.calls.length, 0);

    go('history', 'safety/edit');
    assert.equal(reload.mock.calls.length, 1);
    assert.deepEqual(reload.mock.calls[0][0], {
        only: ['muted', 'blocked'],
        preserveScroll: true,
    });

    stop();
    go('history', 'safety/edit');
    assert.equal(reload.mock.calls.length, 1);
});
