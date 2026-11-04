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

const { trackFeatureFlagsHistory } = await import(
    '../../resources/js/lib/operations-features-history'
);

test('refreshes operations feature flags only on browser history restoration', () => {
    reload.mockClear();
    const stop = trackFeatureFlagsHistory();
    const go = (type: NavigationType, component: string) =>
        navigate?.({ detail: { type, page: { component } } });

    go('initial', 'operations/features');
    go('visit', 'operations/features');
    go('history', 'log/show');
    assert.equal(reload.mock.calls.length, 0);

    go('history', 'operations/features');
    assert.equal(reload.mock.calls.length, 1);
    assert.deepEqual(reload.mock.calls[0][0], {
        only: ['features'],
        preserveScroll: true,
    });

    stop();
    go('history', 'operations/features');
    assert.equal(reload.mock.calls.length, 1);
});
