/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { CSSProperties, ReactElement } from 'react';
import { afterAll, afterEach, test, vi } from 'vite-plus/test';
import type { Embed } from '../../resources/js/types';

const iframeWindow = {} as Window;
const listeners = new Set<(event: MessageEvent<unknown>) => void>();
const cleanups: Array<() => void> = [];
const resizedHeights: number[] = [];

vi.doMock('react', () => ({
    ...React,
    useEffect: (effect: () => void | (() => void)) => {
        const cleanup = effect();

        if (cleanup) {
            cleanups.push(cleanup);
        }
    },
    useRef: () => ({ current: { contentWindow: iframeWindow } }),
    useState: (initial: unknown) => [
        initial,
        (height: number) => resizedHeights.push(height),
    ],
}));

vi.stubGlobal('window', {
    addEventListener: (
        type: string,
        listener: (event: MessageEvent<unknown>) => void,
    ) => {
        if (type === 'message') {
            listeners.add(listener);
        }
    },
    removeEventListener: (
        type: string,
        listener: (event: MessageEvent<unknown>) => void,
    ) => {
        if (type === 'message') {
            listeners.delete(listener);
        }
    },
});

const { EmbedFrame } = await import('../../resources/js/components/link-embed');

afterEach(() => {
    for (const cleanup of cleanups.splice(0)) {
        cleanup();
    }

    listeners.clear();
    resizedHeights.length = 0;
});

afterAll(() => vi.unstubAllGlobals());

function asciinemaRecording(id = '569727'): Embed {
    return {
        provider: 'asciinema',
        kind: 'code',
        src: `https://asciinema.org/a/${id}/iframe`,
        aspect_ratio: null,
        height: 420,
    };
}

function message(
    data: unknown,
    options: { origin?: string; source?: MessageEventSource | null } = {},
): MessageEvent<unknown> {
    return {
        data,
        origin: options.origin ?? 'https://asciinema.org',
        source: options.source ?? iframeWindow,
    } as MessageEvent<unknown>;
}

function dispatch(event: MessageEvent<unknown>) {
    for (const listener of listeners) {
        listener(event);
    }
}

function frame(embed: Embed): ReactElement<{ style: CSSProperties }> {
    return EmbedFrame({ embed, title: 'Player' }) as ReactElement<{
        style: CSSProperties;
    }>;
}

test('uses the configured height until an asciinema recording reports its body size', () => {
    const element = frame(asciinemaRecording());

    assert.deepEqual(element.props.style, { height: 420 });

    dispatch(message({ type: 'bodySize', payload: { height: 318 } }));
    dispatch(message({ type: 'bodySize', payload: { height: 276 } }));

    assert.deepEqual(resizedHeights, [318, 276]);
});

test('only accepts valid body size messages from the recording iframe', () => {
    frame(asciinemaRecording());

    dispatch(
        message(
            { type: 'bodySize', payload: { height: 318 } },
            { origin: 'https://example.com' },
        ),
    );
    dispatch(
        message(
            { type: 'bodySize', payload: { height: 318 } },
            { source: {} as Window },
        ),
    );
    dispatch(message({ type: 'bodySize', payload: { height: 0 } }));
    dispatch(message({ type: 'bodySize', payload: { height: Number.NaN } }));
    dispatch(message({ type: 'bodySize', height: 318 }));
    dispatch(message({ type: 'other', payload: { height: 318 } }));

    assert.deepEqual(resizedHeights, []);
});

test('recognizes asciinema recording IDs with underscores', () => {
    frame(asciinemaRecording('star_wars'));

    dispatch(message({ type: 'bodySize', payload: { height: 318 } }));

    assert.deepEqual(resizedHeights, [318]);
});

test('removes the recording listener when the frame unmounts', () => {
    frame(asciinemaRecording());
    const cleanup = cleanups.pop();
    assert.ok(cleanup);

    cleanup();
    dispatch(message({ type: 'bodySize', payload: { height: 318 } }));

    assert.deepEqual(resizedHeights, []);
});

test('keeps ordinary embeds on their original sizing without a message listener', () => {
    const element = frame({
        provider: 'vimeo',
        kind: 'video',
        src: 'https://player.vimeo.com/video/123',
        aspect_ratio: '16/9',
        height: null,
    });

    assert.deepEqual(element.props.style, { aspectRatio: '16/9' });
    assert.equal(listeners.size, 0);
});
