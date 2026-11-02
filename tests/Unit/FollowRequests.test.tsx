/// <reference types="node" />
import assert from 'node:assert/strict';
import type {
    HttpRequestConfig,
    HttpRequestHandler,
    Page,
    PendingVisit,
} from '@inertiajs/core';
import { afterEach, test, vi } from 'vite-plus/test';

type Listener = (event: {
    detail: { visit: PendingVisit; page: Page };
}) => void;
const listeners = new Map<string, Listener>();
let requestHandler: HttpRequestHandler;
vi.doMock('@inertiajs/core', () => ({
    http: {
        onRequest: (handler: HttpRequestHandler) => {
            requestHandler = handler;
            return () => {};
        },
    },
}));
vi.doMock('@inertiajs/react', () => ({
    router: {
        on: (name: string, listener: Listener) => {
            listeners.set(name, listener);
            return () => listeners.delete(name);
        },
    },
}));
const { trackFollowRequests } =
    await import('../../resources/js/lib/follow-requests');
let stop: VoidFunction;
afterEach(() => stop?.());

function page(ids = ['first', 'twentieth']): Page {
    return {
        component: 'log/show',
        url: '/log/following',
        props: {
            log: 'following',
            commits: { data: ids.map((id) => ({ id })) },
        },
    } as unknown as Page;
}
function visit(id: string, follow = false): PendingVisit {
    return {
        id,
        url: new URL(
            follow ? '/@someone/follow' : '/log/following',
            'http://localhost',
        ),
        method: follow ? 'post' : 'get',
        only: ['commits'],
        headers: follow
            ? { 'X-Follow-Request': 'true', 'X-Log-Through': 'twentieth' }
            : {},
        prefetch: false,
    } as unknown as PendingVisit;
}
function start(visit: PendingVisit): Promise<HttpRequestConfig> {
    listeners.get('before')?.({ detail: { visit, page: page() } });
    return Promise.resolve(
        requestHandler({ method: visit.method,
                url: visit.url.href, headers: visit.headers }),
    );
}
function finish(visit: PendingVisit, next?: Page): void {
    if (next)
        listeners.get('beforeUpdate')?.({ detail: { visit, page: next } });
    listeners.get('finish')?.({ detail: { visit, page: next ?? page() } });
}
async function settle(): Promise<void> {
    await new Promise((resolve) => setTimeout(resolve));
}

test('a follow waits for pagination and uses the boundary that actually reached the page', async () => {
    stop = trackFollowRequests(page());
    const pagination = visit('pagination');
    await start(pagination);
    const follow = visit('follow', true);
    let sent = false;
    const request = start(follow).then((config) => {
        sent = true;
        return config;
    });
    await settle();
    assert.equal(sent, false);
    finish(pagination, page(['first', 'twentieth', 'fortieth']));
    const config = await request;
    assert.equal(config.headers?.['X-Log-Through'], 'fortieth');
    assert.equal(config.headers?.['X-Follow-Queue'], undefined);
    assert.equal(config.headers?.['X-Follow-Request'], undefined);
    finish(follow);
});

test('pagination started during a follow waits until the follow response is applied', async () => {
    stop = trackFollowRequests(page());
    const follow = visit('follow', true);
    await start(follow);
    const pagination = visit('pagination');
    let sent = false;
    const request = start(pagination).then(() => {
        sent = true;
    });
    await settle();
    assert.equal(sent, false);
    finish(follow);
    await request;
    assert.equal(sent, true);
    finish(pagination);
});

test('several follows are all sent in order without cancelling their writes', async () => {
    stop = trackFollowRequests(page());
    const first = visit('first', true);
    const second = visit('second', true);
    const third = visit('third', true);
    const sent: string[] = [];
    await start(first).then(() => sent.push('first'));
    const secondRequest = start(second).then(() => sent.push('second'));
    const thirdRequest = start(third).then(() => sent.push('third'));
    await settle();
    assert.deepEqual(sent, ['first']);
    finish(first);
    await secondRequest;
    assert.deepEqual(sent, ['first', 'second']);
    finish(second);
    await thirdRequest;
    assert.deepEqual(sent, ['first', 'second', 'third']);
    finish(third);
});

test('cancelling a queued read does not let a later follow overtake the active write', async () => {
    stop = trackFollowRequests(page());
    const first = visit('first', true);
    const read = visit('cancelled');
    const last = visit('last', true);
    await start(first);
    const readRequest = start(read);
    finish(read);
    let sent = false;
    const lastRequest = start(last).then(() => {
        sent = true;
    });
    await settle();
    assert.equal(sent, false);
    finish(first);
    await Promise.all([readRequest, lastRequest]);
    assert.equal(sent, true);
    finish(last);
});

test('unrelated reads do not wait for a follow', async () => {
    stop = trackFollowRequests(page());
    const follow = visit('follow', true);
    await start(follow);
    const unrelated = visit('preview');
    unrelated.url = new URL('http://localhost/@another/preview');
    const config = await start(unrelated);
    assert.equal(config.url, unrelated.url.href);
    finish(follow);
});
