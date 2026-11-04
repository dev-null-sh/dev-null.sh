import assert from 'node:assert/strict';
import type { ReloadOptions } from '@inertiajs/core';
import type { PropsWithChildren } from 'react';
import { renderToString } from 'react-dom/server';
import { test, vi } from 'vite-plus/test';

const usePoll = vi.fn(() => ({ start: vi.fn(), stop: vi.fn() }));

vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    Head: () => null,
    Link: ({ children }: PropsWithChildren) => children,
    usePage: () => ({ props: { auth: { user: null } } }),
    usePoll,
}));
vi.doMock('@/hooks/use-realtime', () => ({ useIsListening: () => false }));
vi.doMock('@/layouts/app-layout', () => ({
    default: ({ children }: PropsWithChildren) => children,
}));

const { default: ScheduledCommits } =
    await import('../../resources/js/pages/scheduled-commits/index');

test('scheduled commits pause polling in hidden tabs without changing the partial reload', () => {
    usePoll.mockClear();

    renderToString(
        <ScheduledCommits
            scheduledCommits={{
                data: [],
                prev_page_url: null,
                next_page_url: null,
            }}
        />,
    );

    assert.equal(usePoll.mock.calls.length, 1);
    const [interval, reload, options] = usePoll.mock.calls[0] as unknown as [
        number,
        () => ReloadOptions,
        { autoStart: boolean; background: string },
    ];

    assert.equal(interval, 30_000);
    assert.deepEqual(reload().only, ['scheduledCommits']);
    assert.deepEqual(options, { autoStart: false, background: 'pause' });
});
