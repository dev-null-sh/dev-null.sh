/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterAll, beforeEach, test, vi } from 'vite-plus/test';

let layoutEffect: () => void = () => {};
const setIsListening = vi.fn();

// Renders the hook once: effects run when the test says React has written the new body.
vi.doMock('react', () => ({
    ...React,
    useLayoutEffect: (effect: () => void) => {
        layoutEffect = effect;
    },
    useEffect: () => {},
    useRef: (initial: unknown) => ({ current: initial }),
    useState: (initial: unknown) => [initial, setIsListening],
    useSyncExternalStore: (_subscribe: unknown, snapshot: () => unknown) =>
        snapshot(),
}));

const toastError = vi.fn();
vi.doMock('sonner', () => ({ toast: { error: toastError } }));

type Listener = {
    lang: string;
    continuous: boolean;
    start: () => void;
    onresult: (event: {
        resultIndex: number;
        results: ({ transcript: string }[] & { isFinal: boolean })[];
    }) => void;
    onerror: (event: { error: string }) => void;
    onend: () => void;
};

let listener: Listener;

vi.stubGlobal('window', {
    webkitSpeechRecognition: class {
        start = vi.fn();
        stop = vi.fn();
        abort = vi.fn();

        constructor() {
            listener = this as unknown as Listener;
        }
    },
});
vi.stubGlobal('document', { documentElement: { lang: 'fr' } });

afterAll(() => {
    vi.doUnmock('react');
    vi.doUnmock('sonner');
    vi.unstubAllGlobals();
});

const { useDictation } = await import('../../resources/js/hooks/use-dictation');

function said(transcript: string, isFinal = true) {
    return Object.assign([{ transcript }], { isFinal });
}

function textareaAt(selectionStart: number, selectionEnd = selectionStart) {
    const focus = vi.fn();
    const setSelectionRange = vi.fn();
    const textarea = {
        selectionStart,
        selectionEnd,
        focus,
        setSelectionRange,
    } as unknown as HTMLTextAreaElement;

    return { textarea, focus, setSelectionRange };
}

const updateBody = vi.fn();

beforeEach(() => {
    vi.clearAllMocks();
});

test('listens in the page language and inserts each finished phrase at the caret', () => {
    const { textarea, focus, setSelectionRange } = textareaAt(7);
    const dictation = useDictation({
        body: 'Shipped today',
        updateBody,
        textareaRef: { current: textarea },
        maxLength: 280,
    });

    assert.equal(dictation.isSupported, true);
    dictation.start();
    assert.equal(listener.lang, 'fr');
    assert.equal(listener.continuous, true);
    assert.deepEqual(setIsListening.mock.calls, [[true]]);

    listener.onresult({
        resultIndex: 0,
        results: [said('the dark mode', false), said(' the dark mode ')],
    });
    layoutEffect();

    assert.deepEqual(updateBody.mock.calls, [['Shipped the dark mode today']]);
    assert.equal(focus.mock.calls.length, 1);
    assert.deepEqual(setSelectionRange.mock.calls, [[21, 21]]);
});

test('ignores silence and phrases that would overflow the commit', () => {
    const dictation = useDictation({
        body: 'abc',
        updateBody,
        textareaRef: { current: textareaAt(3).textarea },
        maxLength: 5,
    });

    dictation.start();
    listener.onresult({
        resultIndex: 0,
        results: [said('   '), said('too long')],
    });

    assert.equal(updateBody.mock.calls.length, 0);
});

test('says when the microphone is blocked, but not when nothing was said', () => {
    const dictation = useDictation({
        body: '',
        updateBody,
        textareaRef: { current: null },
        maxLength: 280,
    });

    dictation.start();
    listener.onerror({ error: 'no-speech' });
    listener.onerror({ error: 'not-allowed' });
    listener.onend();

    assert.deepEqual(toastError.mock.calls, [
        ['Allow the microphone to dictate your commit.'],
    ]);
    assert.deepEqual(setIsListening.mock.calls, [[true], [false]]);
});
