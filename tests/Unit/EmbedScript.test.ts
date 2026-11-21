import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

type Listener = (event: {
    origin: string;
    source: unknown;
    data: unknown;
}) => void;

type Frame = {
    src: string;
    contentWindow: object;
    style: { height?: string };
};

function frame(src: string): Frame {
    return { src, contentWindow: {}, style: {} };
}

function load(frames: Frame[]) {
    const listeners: Listener[] = [];
    const context = {
        URL,
        document: {
            currentScript: { src: 'https://dev-null.sh/embed.js' },
            querySelectorAll: (selector: string) => {
                const prefix = /\^="([^"]+)"/.exec(selector)?.[1] ?? '';

                return frames.filter((frame) => frame.src.startsWith(prefix));
            },
        },
        window: {
            addEventListener: (type: string, listener: Listener) => {
                if (type === 'message') {
                    listeners.push(listener);
                }
            },
        } as Record<string, unknown>,
    };

    vm.runInNewContext(readFileSync('public/embed.js', 'utf8'), context);

    return (event: { origin: string; source: unknown; data: unknown }) =>
        listeners.forEach((listener) => listener(event));
}

void test('sizes the frame that sent its height', () => {
    const card = frame('https://dev-null.sh/embed/1?signature=a');
    const other = frame('https://dev-null.sh/embed/2?signature=b');
    const post = load([card, other]);

    post({
        origin: 'https://dev-null.sh',
        source: card.contentWindow,
        data: { type: 'devnull:embed:resize', height: 412.4 },
    });

    assert.equal(card.style.height, '413px');
    assert.equal(other.style.height, undefined);
});

void test('ignores messages from other origins, of other types, or with no usable height', () => {
    const card = frame('https://dev-null.sh/embed/1?signature=a');
    const post = load([card]);

    post({
        origin: 'https://evil.example',
        source: card.contentWindow,
        data: { type: 'devnull:embed:resize', height: 300 },
    });
    post({
        origin: 'https://dev-null.sh',
        source: card.contentWindow,
        data: { type: 'other', height: 300 },
    });
    post({
        origin: 'https://dev-null.sh',
        source: card.contentWindow,
        data: { type: 'devnull:embed:resize', height: 'tall' },
    });
    post({
        origin: 'https://dev-null.sh',
        source: card.contentWindow,
        data: null,
    });

    assert.equal(card.style.height, undefined);
});

void test('listens once when the script is included several times', () => {
    const card = frame('https://dev-null.sh/embed/1?signature=a');
    const listeners: Listener[] = [];
    const context = {
        URL,
        document: {
            currentScript: { src: 'https://dev-null.sh/embed.js' },
            querySelectorAll: () => [card],
        },
        window: {
            addEventListener: (_: string, listener: Listener) =>
                listeners.push(listener),
        } as Record<string, unknown>,
    };
    const source = readFileSync('public/embed.js', 'utf8');

    vm.runInNewContext(source, context);
    vm.runInNewContext(source, context);

    assert.equal(listeners.length, 1);
});
