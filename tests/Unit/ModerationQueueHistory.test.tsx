import assert from 'node:assert/strict';
import { test, vi } from 'vite-plus/test';

type NavigationType = 'initial' | 'visit' | 'history';
type Navigation = {
    detail: {
        type: NavigationType;
        page: { component: string; url: string };
    };
};

const visit = vi.fn();
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
        visit,
    },
}));
vi.doMock('@/lib/current-page', () => ({
    currentPageComponent: () => current,
}));

const { trackModerationQueueHistory } = await import(
    '../../resources/js/lib/moderation-queue-history'
);

test('refreshes the moderation queue on history and bfcache restores', () => {
    const events = new Map<string, (event: { persisted: boolean }) => void>();
    vi.stubGlobal('window', {
        location: { href: 'https://dev-null.sh/moderation' },
        addEventListener: (name: string, listener: (event: { persisted: boolean }) => void) =>
            events.set(name, listener),
        removeEventListener: (name: string) => events.delete(name),
    });

    try {
        visit.mockClear();
        const stop = trackModerationQueueHistory();
        const go = (type: NavigationType, component: string) =>
            navigate?.({
                detail: { type, page: { component, url: '/moderation' } },
            });

        go('initial', 'moderation/show');
        go('visit', 'moderation/show');
        go('history', 'log/show');
        assert.equal(visit.mock.calls.length, 0);

        go('history', 'moderation/show');
        assert.equal(visit.mock.calls.length, 1);
        assert.equal(visit.mock.calls[0][0], '/moderation');
        assert.deepEqual(visit.mock.calls[0][1], {
            only: ['commits', 'hasUnmoderatedCommits'],
            reset: ['commits'],
            preserveState: true,
            preserveScroll: false,
        });

        current = 'moderation/show';
        events.get('pageshow')?.({ persisted: false });
        assert.equal(visit.mock.calls.length, 1);
        events.get('pageshow')?.({ persisted: true });
        assert.equal(visit.mock.calls.length, 2);
        assert.equal(visit.mock.calls[1][0], 'https://dev-null.sh/moderation');

        stop();
        assert.equal(events.has('pageshow'), false);
        go('history', 'moderation/show');
        assert.equal(visit.mock.calls.length, 2);
    } finally {
        vi.unstubAllGlobals();
    }
});
