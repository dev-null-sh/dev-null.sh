import assert from 'node:assert/strict';
import { test, vi } from 'vite-plus/test';

vi.mock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { auth: { user: null } } }),
}));
const { isEditable } = await import('../../resources/js/components/commit-edit');
const { useCommitTranslation } = await import('../../resources/js/components/commit-translation');
const { renderToString } = await import('react-dom/server');
import type { Commit } from '../../resources/js/types';

const created = '2026-10-20T12:00:00.000000Z';
const commit = { body: 'Corrected', created_at: created, status: 'approved', body_segments: [{ type: 'text', text: 'Corrected' }], translation: null } as Commit;

test('amend eligibility ends exactly at the deadline and respects locks, not review state', () => {
    assert.equal(isEditable(commit, new Date('2026-10-20T12:14:59Z')), true);
    assert.equal(isEditable(commit, new Date('2026-10-20T12:15:00Z')), false);
    for (const changed of [{ amendment_locked_at: created }, { replies_count: 1 }]) {
        assert.equal(isEditable({ ...commit, ...changed }, new Date(created)), false);
    }
    for (const status of ['pending', 'rejected'] as const) {
        assert.equal(isEditable({ ...commit, edited_at: created, status }, new Date(created)), true);
    }
});

test('clearing translation on amendment renders the corrected body', () => {
    function Probe() {
        const translation = useCommitTranslation(commit);
        assert.deepEqual(translation.segments, commit.body_segments);
        return null;
    }
    renderToString(<Probe />);
});
