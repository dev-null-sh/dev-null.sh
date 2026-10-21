/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { beforeEach, test, vi } from 'vite-plus/test';
import type { Embed, LinkPreview } from '../../resources/js/types';

type Element = ReactElement<Record<string, unknown>>;
type Effect = () => void | (() => void);
type Deferred<T> = {
    promise: Promise<T>;
    resolve(value: T): void;
    reject(reason?: unknown): void;
};

let state: unknown[] = [];
let stateCursor = 0;
let refCursor = 0;
let refs: { current: unknown }[] = [];
let effects: Effect[] = [];
let container: HTMLDivElement;

function deferred<T>(): Deferred<T> {
    let resolve!: (value: T) => void;
    let reject!: (reason?: unknown) => void;
    const promise = new Promise<T>((resolvePromise, rejectPromise) => {
        resolve = resolvePromise;
        reject = rejectPromise;
    });

    return { promise, resolve, reject };
}

function resetHooks() {
    state = [];
    stateCursor = 0;
    refCursor = 0;
    refs = [];
    effects = [];
    container = {} as HTMLDivElement;
}

function beginRender() {
    stateCursor = 0;
    refCursor = 0;
}

function render<T>(component: () => T): T {
    beginRender();

    return component();
}

function elements(
    node: ReactNode,
    preserve: readonly unknown[] = [],
): Element[] {
    if (!React.isValidElement(node)) {
        return [];
    }

    const element = node as Element;

    if (
        typeof element.type === 'function' &&
        !preserve.includes(element.type)
    ) {
        return elements(
            (element.type as (props: typeof element.props) => ReactNode)(
                element.props,
            ),
            preserve,
        );
    }

    return [
        element,
        ...React.Children.toArray(element.props.children as ReactNode).flatMap(
            (child) => elements(child, preserve),
        ),
    ];
}

function findByTest(
    node: ReactNode,
    value: string,
    preserve: readonly unknown[] = [],
): Element | undefined {
    return elements(node, preserve).find(
        (element) => element.props['data-test'] === value,
    );
}

function flush() {
    return Promise.resolve()
        .then(() => Promise.resolve())
        .then(() => Promise.resolve())
        .then(() => Promise.resolve());
}

function installReactMock() {
    vi.doMock('react', () => ({
        ...React,
        useEffect: (effect: Effect) => {
            effects.push(effect);
        },
        useRef: (initial: unknown) => {
            const index = refCursor++;
            refs[index] ??= {
                current: initial === null ? container : initial,
            };

            return refs[index];
        },
        useState: (initial: unknown) => {
            const index = stateCursor++;
            state[index] ??=
                typeof initial === 'function'
                    ? (initial as () => unknown)()
                    : initial;

            return [
                state[index],
                (value: unknown) => {
                    state[index] =
                        typeof value === 'function'
                            ? (value as (previous: unknown) => unknown)(
                                  state[index],
                              )
                            : value;
                },
            ];
        },
    }));
}

const preview: LinkPreview = {
    url: 'https://asciinema.org/s/LiveToken',
    title: 'A real live stream',
    description: null,
    image_url: 'https://asciinema.org/a/LiveToken.png',
    site_name: 'asciinema',
    is_pending: false,
};

const streamEmbed: Embed = {
    provider: 'asciinema',
    kind: 'code',
    src: 'https://asciinema.org/s/LiveToken',
    aspect_ratio: null,
    height: 420,
    poster: 'https://asciinema.org/a/LiveToken.png',
    renderer: 'asciinema-stream',
    stream_url: 'wss://asciinema.org/ws/s/LiveToken',
};

beforeEach(() => {
    vi.resetModules();
    vi.clearAllMocks();
    resetHooks();
    installReactMock();
});

test('shows a stream thumbnail first and does not turn static quotes into player controls', async () => {
    const { LinkEmbed } =
        await import('../../resources/js/components/link-embed');

    const interactive = render(() =>
        LinkEmbed({ preview, embed: streamEmbed }),
    );
    const staticQuote = render(() =>
        LinkEmbed({ preview, embed: streamEmbed, isStatic: true }),
    );

    assert.ok(findByTest(interactive, 'link-embed-play'));
    assert.equal(
        elements(interactive).some(
            (element) =>
                element.type === 'iframe' ||
                element.type === 'asciinema-player',
        ),
        false,
    );
    assert.equal(effects.length, 0);
    assert.equal(findByTest(staticQuote, 'link-embed-play'), undefined);
    assert.equal(
        elements(staticQuote).some((element) => element.type === 'button'),
        false,
    );
    assert.equal(effects.length, 0);
});

test('replaces the stream thumbnail with the stream component after play', async () => {
    const { AsciinemaStream } =
        await import('../../resources/js/components/asciinema-stream');
    const { LinkEmbed } =
        await import('../../resources/js/components/link-embed');

    let view = render(() => LinkEmbed({ preview, embed: streamEmbed }));
    const trigger = findByTest(view, 'link-embed-play')!;
    (trigger.props.onClick as () => void)();
    view = render(() => LinkEmbed({ preview, embed: streamEmbed }));

    const stream = elements(view, [AsciinemaStream]).find(
        (element) => element.type === AsciinemaStream,
    );
    assert.equal(stream?.props.src, streamEmbed.stream_url);
    assert.equal(
        elements(view).some((element) => element.type === 'iframe'),
        false,
    );
});

test('does not create a player when its imports finish after unmount', async () => {
    const playerLoad = deferred<{ create: ReturnType<typeof vi.fn> }>();
    const cssLoad = deferred<Record<string, never>>();
    const create = vi.fn();
    vi.doMock('asciinema-player', () => playerLoad.promise);
    vi.doMock(
        'asciinema-player/dist/bundle/asciinema-player.css',
        () => cssLoad.promise,
    );
    const { AsciinemaStream } =
        await import('../../resources/js/components/asciinema-stream');

    render(() =>
        AsciinemaStream({
            src: streamEmbed.stream_url,
            url: preview.url,
            title: preview.title!,
            height: 420,
        }),
    );
    const cleanup = effects[0]!();
    cleanup?.();
    playerLoad.resolve({ create });
    cssLoad.resolve({});
    await flush();

    assert.equal(create.mock.calls.length, 0);
});

test('creates a player only after mount and disposes it when unmounted', async () => {
    const player = { dispose: vi.fn() };
    const create = vi.fn(() => player);
    const playerLoad = vi.fn(() => Promise.resolve({ create }));
    const cssLoad = vi.fn(() => Promise.resolve({}));
    vi.doMock('asciinema-player', playerLoad);
    vi.doMock('asciinema-player/dist/bundle/asciinema-player.css', cssLoad);
    const { AsciinemaStream } =
        await import('../../resources/js/components/asciinema-stream');

    render(() =>
        AsciinemaStream({
            src: streamEmbed.stream_url,
            url: preview.url,
            title: preview.title!,
            height: 420,
        }),
    );
    assert.equal(playerLoad.mock.calls.length, 0);
    assert.equal(cssLoad.mock.calls.length, 0);

    const cleanup = effects[0]!();
    await flush();
    assert.equal(playerLoad.mock.calls.length, 1);
    assert.equal(cssLoad.mock.calls.length, 1);
    await flush();
    assert.deepEqual(create.mock.calls[0], [
        streamEmbed.stream_url,
        container,
        {
            autoPlay: true,
            fit: 'width',
            controls: true,
        },
    ]);

    cleanup?.();
    assert.equal(player.dispose.mock.calls.length, 1);
});

test('shows the original asciinema link when loading the player fails', async () => {
    vi.doMock('asciinema-player', () => Promise.reject(new Error('offline')));
    vi.doMock('asciinema-player/dist/bundle/asciinema-player.css', () =>
        Promise.resolve({}),
    );
    const { AsciinemaStream } =
        await import('../../resources/js/components/asciinema-stream');

    render(() =>
        AsciinemaStream({
            src: streamEmbed.stream_url,
            url: preview.url,
            title: preview.title!,
            height: 420,
        }),
    );
    effects[0]!();
    await flush();
    await new Promise((resolve) => setTimeout(resolve));
    await flush();
    const failed = render(() =>
        AsciinemaStream({
            src: streamEmbed.stream_url,
            url: preview.url,
            title: preview.title!,
            height: 420,
        }),
    );

    const status = elements(failed).find(
        (element) => element.props.role === 'status',
    );
    const source = elements(failed).find((element) => element.type === 'a');
    assert.match(
        String(status?.props.children),
        /Could not load the live stream/,
    );
    assert.equal(source?.props.href, preview.url);
});
