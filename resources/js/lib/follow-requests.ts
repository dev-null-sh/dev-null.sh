import { http } from '@inertiajs/core';
import type { Page } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import type { Commit, CursorPaginated } from '@/types';

/**
 * Follow responses and log reads must finish in order, including their Inertia swaps.
 * A queued follow takes its boundary after earlier pagination has reached the page.
 */
export function trackFollowRequests(initialPage: Page): VoidFunction {
    let page = initialPage;
    let tail = Promise.resolve();
    const pending = new Map<
        string,
        {
            ready: Promise<void>;
            finish: VoidFunction;
            component: string;
            log: unknown;
        }
    >();
    const removeBefore = router.on('before', ({ detail: { visit } }) => {
        const isFollow = visit.headers['X-Follow-Request'] === 'true';
        const isLogRead =
            page.component === 'log/show' &&
            visit.method === 'get' &&
            visit.only.length > 0 &&
            visit.url.pathname === new URL(page.url, visit.url).pathname;

        if (visit.prefetch || (!isFollow && !isLogRead)) {
            return;
        }

        let finish!: VoidFunction;
        const finished = new Promise<void>((resolve) => {
            finish = resolve;
        });
        pending.set(visit.id, {
            ready: tail,
            finish,
            component: page.component,
            log: page.props.log,
        });
        tail = Promise.all([tail, finished]).then(() => undefined);
        visit.headers['X-Follow-Queue'] = visit.id;
    });
    const removeRequest = http.onRequest(async (config) => {
        const headers = { ...config.headers };
        const id = headers['X-Follow-Queue'];
        const request = typeof id === 'string' ? pending.get(id) : undefined;
        const isFollow = headers['X-Follow-Request'] === 'true';
        delete headers['X-Follow-Queue'];
        delete headers['X-Follow-Request'];

        if (request) {
            await request.ready;

            if (
                isFollow &&
                request.component === 'log/show' &&
                request.log === 'following' &&
                page.component === request.component &&
                page.props.log === request.log
            ) {
                const commits = page.props.commits as CursorPaginated<Commit>;
                const through = commits.data.at(-1)?.id;
                delete headers['X-Log-Through'];

                if (through) {
                    headers['X-Log-Through'] = through;
                }
            }
        }

        return { ...config, headers };
    });
    const removeFinish = router.on('finish', ({ detail: { visit } }) => {
        pending.get(visit.id)?.finish();
        pending.delete(visit.id);
    });
    const removeUpdate = router.on('beforeUpdate', (event) => {
        page = event.detail.page;
    });
    const removeNavigate = router.on('navigate', (event) => {
        page = event.detail.page;
    });

    return () => {
        removeBefore();
        removeRequest();
        removeFinish();
        removeUpdate();
        removeNavigate();
        pending.forEach((request) => request.finish());
    };
}
