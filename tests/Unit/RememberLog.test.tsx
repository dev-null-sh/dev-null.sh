/// <reference types="node" />
import assert from 'node:assert/strict';
import { afterEach, test, vi } from 'vite-plus/test';
import type { Log } from '../../resources/js/types';
import { hookHarness } from './HookHarness';

const harness = hookHarness();
const page = {
    component: 'log/show',
    url: '/log/following',
    props: {} as {
        auth: { user: { id: string }; log: Log };
        log?: Log;
        commits?: { data: never[] };
    },
};
const saves: { log: Log; done: () => void }[] = [];
const replaced: [string, Log][] = [];
const reloads: string[][] = [];
type VisitEvent = { detail: { visit: { only: string[] } } };
const listeners = {
    start: new Set<(event: VisitEvent) => void>(),
    finish: new Set<(event: VisitEvent) => void>(),
};
let payload = (): { log: Log } => ({ log: 'following' });
const transform = (data: () => { log: Log }) => {
    payload = data;
};
const put = () =>
    new Promise<void>((resolve) => {
        saves.push({ log: payload().log, done: resolve });
    });

vi.doMock('react', () => harness.react);
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => page,
    useHttp: () => ({ put, transform }),
    router: {
        replaceProp: (prop: string, value: Log) => {
            replaced.push([prop, value]);
            page.props.auth.log = value;
        },
        reload: (options: { only?: string[]; onFinish?: () => void }) => {
            reloads.push(options.only ?? []);
            options.onFinish?.();
        },
        on: (
            name: keyof typeof listeners,
            listener: (event: VisitEvent) => void,
        ) => {
            listeners[name].add(listener);

            return () => listeners[name].delete(listener);
        },
    },
}));
vi.doMock('sonner', () => ({
    toast: { error: () => assert.fail('unexpected save failure') },
}));

const { useRememberLog } =
    await import('../../resources/js/hooks/use-remember-log');

function RememberLog() {
    useRememberLog();

    return null;
}

const show = (log: Log, remembered: Log) => {
    page.component = 'log/show';
    page.url = `/log/${log}`;
    page.props = {
        auth: { user: { id: 'viewer' }, log: remembered },
        log,
        commits: { data: [] },
    };
};

const profile = (remembered: Log) => {
    page.component = 'user/show';
    page.url = '/@someone';
    page.props = { auth: { user: { id: 'viewer' }, log: remembered } };
};

/** Renders again whenever the hook replaced the remembered log, as the page update that follows would. */
const rerender = () => {
    let remembered: Log;

    do {
        remembered = page.props.auth.log;
        harness.rerender();
    } while (page.props.auth.log !== remembered);
};

const emit = (name: keyof typeof listeners, only: string[]) =>
    listeners[name].forEach((listener) =>
        listener({ detail: { visit: { only } } }),
    );

/** A request reads the remembered log again, and the page it answers with shows it. */
const hear = (log: Log, url = page.url) => {
    emit('start', ['auth.log']);
    page.url = url;
    page.props.auth.log = log;
    rerender();
    emit('finish', ['auth.log']);
};

const settle = () => new Promise<void>((resolve) => setImmediate(resolve));

afterEach(() => {
    harness.unmount();
    saves.length = 0;
    replaced.length = 0;
    reloads.length = 0;
});

test("a log another page remembered shows here without saving this page's log again", async () => {
    show('latest', 'latest');
    void harness.render(RememberLog, {});
    hear('trending');

    assert.equal(saves.length, 0);
    assert.equal(replaced.length, 0);

    show('following', 'trending');
    rerender();

    assert.deepEqual(
        saves.map((save) => save.log),
        ['following'],
    );
    assert.deepEqual(replaced, [['auth.log', 'following']]);

    saves[0]?.done();
    await settle();

    assert.equal(reloads.length, 0);
});

test('a log heard while this page is still saving its own is read again once the save is done', async () => {
    show('latest', 'trending');
    void harness.render(RememberLog, {});
    rerender();

    assert.deepEqual(
        saves.map((save) => save.log),
        ['latest'],
    );
    assert.deepEqual(replaced, [['auth.log', 'latest']]);

    hear('trending');

    assert.equal(page.props.auth.log, 'latest');
    assert.equal(reloads.length, 0);

    saves[0]?.done();
    await settle();

    assert.deepEqual(reloads, [['auth.log']]);

    hear('trending');

    assert.equal(page.props.auth.log, 'trending');
    assert.equal(saves.length, 1);
});

test('going back to a log remembers it again, instead of taking the log that page showed then', async () => {
    show('following', 'following');
    void harness.render(RememberLog, {});
    show('latest', 'following');
    rerender();
    saves[0]?.done();
    await settle();

    // History brings the Following page back as it was, with the log it showed then.
    show('following', 'following');
    rerender();

    assert.deepEqual(
        saves.map((save) => save.log),
        ['latest', 'following'],
    );
});

test('a page from history keeps the log remembered since it was shown', async () => {
    profile('following');
    void harness.render(RememberLog, {});
    show('latest', 'following');
    rerender();
    saves[0]?.done();
    await settle();

    profile('following');
    rerender();

    assert.equal(page.props.auth.log, 'latest');
    assert.equal(saves.length, 1);
});

test('a log read again shows even when the page comes back with its address written differently', () => {
    page.component = 'search/index';
    page.url = '/search?q=foo+bar';
    page.props = { auth: { user: { id: 'viewer' }, log: 'latest' } };
    void harness.render(RememberLog, {});
    hear('trending', '/search?q=foo%20bar');

    assert.equal(page.props.auth.log, 'trending');
    assert.equal(replaced.length, 0);
});
