import assert from 'node:assert/strict';
import { afterEach, test, vi } from 'vite-plus/test';
import type { Commit, CursorPaginated } from '../../resources/js/types';
import { hookHarness, find } from './HookHarness';

const harness = hookHarness();
const flashes = new Set<
    (event: { detail: { flash: Record<string, unknown> } }) => void
>();
vi.doMock('react', () => harness.react);
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({
        props: { auth: { user: { id: 'viewer' }, pasteis: null }, flash: {} },
    }),
    router: {
        on: (
            _event: string,
            callback: (event: {
                detail: { flash: Record<string, unknown> };
            }) => void,
        ) => {
            flashes.add(callback);
            return () => flashes.delete(callback);
        },
    },
}));
vi.doMock('../../resources/js/hooks/use-live-stream', () => ({
    useLiveStream: () => null,
}));
const { default: Show } = await import('../../resources/js/pages/user/show');
const { deletedResources } =
    await import('../../resources/js/lib/deleted-resources');
const commit = (id: string, minute: string) =>
    ({ id, created_at: `2026-11-03T10:${minute}:00Z` }) as Commit;
const pin = commit('old-pin', '20');
const props = {
    user: {
        id: 'viewer',
        handle: 'viewer',
        name: 'Viewer',
        lane: 1,
        followers_count: 0,
        following_count: 0,
        created_at: '2026-01-01T00:00:00Z',
        bio: '',
    },
    bioProfiles: {},
    pasteisReceived: null,
    isFollowing: false,
    followsYou: false,
    isMuting: false,
    followersYouKnow: null,
    canPush: false,
    moderation: null,
    activity: {},
    streaks: {},
    achievements: {},
    commits: {
        data: [commit('newer', '30'), commit('older', '10')],
        next_cursor: null,
    } as CursorPaginated<Commit>,
    tab: 'commits',
    pinnedCommit: pin,
    readme: null,
} as unknown as Parameters<typeof Show>[0];
function render(pinnedCommit: Commit | null) {
    const tree = harness.render(Show, { ...props, pinnedCommit });
    const feed = find(tree, (p) => 'commits' in p && 'pinnedCommit' in p);
    assert.ok(feed);
    return (feed.props.commits as CursorPaginated<Commit>).data.map(
        ({ id }) => id,
    );
}
afterEach(() => {
    harness.unmount();
    deletedResources.restoreCommit(pin.id);
    flashes.clear();
});

test('a real unpin returns, local deletion hides it, and undo brings it back', () => {
    render(pin);
    render(null);
    assert.deepEqual(render(null), ['newer', 'old-pin', 'older']);
    flashes.forEach((notify) =>
        notify({ detail: { flash: { deleted: pin } } }),
    );
    assert.deepEqual(render(null), ['newer', 'older']);
    flashes.forEach((notify) =>
        notify({ detail: { flash: { restored: pin } } }),
    );
    assert.deepEqual(render(null), ['newer', 'old-pin', 'older']);
});

test('a former pin hidden by another device does not become an active feed card', () => {
    render(pin);
    deletedResources.markCommit(pin.id);
    render(null);
    assert.deepEqual(render(null), ['newer', 'older']);
});
