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

const { trackModerationQueueHistory } = await import(
    '../../resources/js/lib/moderation-queue-history'
);

test('refreshes pending moderation queue only on browser history restoration', () => {
    reload.mockClear();
    const stop = trackModerationQueueHistory();
    const go = (type: NavigationType, component: string) =>
        navigate?.({ detail: { type, page: { component } } });

    go('initial', 'moderation/show');
    go('visit', 'moderation/show');
    go('history', 'log/show');
    assert.equal(reload.mock.calls.length, 0);

    go('history', 'moderation/show');
    assert.equal(reload.mock.calls.length, 1);
    assert.deepEqual(reload.mock.calls[0][0], {
        only: ['commits', 'hasUnmoderatedCommits'],
        reset: ['commits'],
        preserveScroll: false,
    });

    stop();
    go('history', 'moderation/show');
    assert.equal(reload.mock.calls.length, 1);
});
