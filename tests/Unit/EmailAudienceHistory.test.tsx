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

const { trackEmailAudienceHistory } = await import(
    '../../resources/js/lib/email-audience-history'
);

test('refreshes email recipient estimate only on browser history restoration', () => {
    reload.mockClear();
    const stop = trackEmailAudienceHistory();
    const go = (type: NavigationType, component: string) =>
        navigate?.({ detail: { type, page: { component } } });

    go('initial', 'operations/emails');
    go('visit', 'operations/emails');
    go('history', 'log/show');
    assert.equal(reload.mock.calls.length, 0);

    go('history', 'operations/emails');
    assert.equal(reload.mock.calls.length, 1);
    assert.deepEqual(reload.mock.calls[0][0], {
        only: ['recipients'],
        preserveScroll: true,
    });

    stop();
    go('history', 'operations/emails');
    assert.equal(reload.mock.calls.length, 1);
});
