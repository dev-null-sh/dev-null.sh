/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { GifProvider } from '../../resources/js/lib/gif-providers';

const originalWindow = globalThis.window;

let state: unknown[] = [];
let cursor = 0;

vi.doMock('react', () => ({
    ...React,
    useEffect: (effect: () => void) => effect(),
    useMemo: (factory: () => unknown) => factory(),
    useId: () => `preview-${cursor++}`,
    useSyncExternalStore: (_subscribe: unknown, snapshot: () => unknown) =>
        snapshot(),
    useRef: (initial: unknown) => {
        const index = cursor++;

        if (!(index in state)) {
            state[index] = { current: initial };
        }

        return state[index];
    },
    useState: (initial: unknown) => {
        const index = cursor++;

        if (!(index in state)) {
            state[index] = typeof initial === 'function' ? initial() : initial;
        }

        return [
            state[index],
            (value: unknown) => {
                state[index] =
                    typeof value === 'function'
                        ? (value as (current: unknown) => unknown)(state[index])
                        : value;
            },
        ];
    },
}));

vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { auth: { user: { id: 'user-1' } } } }),
}));

const result = {
    id: 'one',
    url: 'https://static.klipy.com/ii/one.gif',
    pageUrl: 'https://klipy.com/gifs/one',
    title: 'One',
    username: null,
    width: 100,
    height: 100,
    previewUrl: 'https://static.klipy.com/ii/one-preview.gif',
};

const search = vi.fn<GifProvider['search']>(async () => ({
    results: [result],
    nextCursor: null,
}));
const trending = vi.fn<GifProvider['trending']>(async () => ({
    results: [result],
    nextCursor: null,
}));

const provider: GifProvider = {
    id: 'klipy',
    label: 'FAUX',
    searchPlaceholder: 'Find FAUX GIFs',
    attribution: { label: 'Powered by FAUX', href: 'https://faux.example' },
    search,
    trending,
    validateKey: vi.fn(async () => 'valid' as const),
};

const { GifPickerDialog } = await import(
    '../../resources/js/components/gif-picker-dialog'
);
const { ComposerMediaButton } = await import(
    '../../resources/js/components/commit-image-picker'
);
const { gifProviderFor } = await import(
    '../../resources/js/lib/gif-providers'
);

let store: Map<string, string>;

type Element = ReactElement<Record<string, unknown>>;

function elements(node: ReactNode): Element[] {
    if (!React.isValidElement(node)) {
        return [];
    }

    const element = node as Element;

    return [
        element,
        ...React.Children.toArray(element.props.children as ReactNode).flatMap(
            elements,
        ),
    ];
}

function render(open = true, onPick = vi.fn()): Element[] {
    cursor = 0;

    return elements(
        GifPickerDialog({
            provider,
            apiKey: 'k',
            open,
            onOpenChange: vi.fn(),
            onPick,
        }),
    );
}

function settle(): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, 0));
}

function field(fields: Element[], testId: string): Element {
    const found = fields.find(
        (element) => element.props['data-test'] === testId,
    );

    assert.ok(found, `no element with data-test="${testId}"`);

    return found;
}

beforeEach(() => {
    state = [];
    cursor = 0;
    store = new Map();
    globalThis.window = {
        localStorage: {
            getItem: (key: string) => store.get(key) ?? null,
            setItem: (key: string, value: string) => store.set(key, value),
            removeItem: (key: string) => store.delete(key),
            key: (index: number) => [...store.keys()][index] ?? null,
            get length() {
                return store.size;
            },
        },
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    } as unknown as Window & typeof globalThis;
    vi.clearAllMocks();
    search.mockImplementation(async () => ({
        results: [result],
        nextCursor: null,
    }));
    trending.mockImplementation(async () => ({
        results: [result],
        nextCursor: null,
    }));
});

afterEach(() => {
    globalThis.window = originalWindow;
});

test('uses the provider placeholder and attribution', () => {
    const fields = render(false);

    assert.equal(
        field(fields, 'gif-search-input').props.placeholder,
        'Find FAUX GIFs',
    );
    assert.ok(
        fields.some((element) => element.props.children === 'Powered by FAUX'),
    );
    assert.ok(
        fields.some(
            (element) => element.props.href === 'https://faux.example',
        ),
    );
});

test('searches through the injected provider', async () => {
    render();
    await vi.waitFor(() => {
        assert.equal(trending.mock.calls.length, 1);
    });
    assert.equal(search.mock.calls.length, 0);
});

test('searches with the typed query', async () => {
    const fields = render();

    (
        field(fields, 'gif-search-input').props.onChange as (event: {
            target: { value: string };
        }) => void
    )({ target: { value: 'cats' } });

    render();

    await vi.waitFor(() => {
        assert.equal(search.mock.calls.length, 1);
    });
    assert.deepEqual(search.mock.calls[0].slice(0, 2), ['k', 'cats']);
});

test('picks the clicked result through the injected provider', async () => {
    const onPick = vi.fn();
    render(true, onPick);
    await settle();

    const fields = render(true, onPick);
    const button = elements(field(fields, 'gif-results')).find(
        (element) => element.type === 'button',
    );

    assert.ok(button);
    (button.props.onClick as () => void)();

    assert.deepEqual(onPick.mock.calls[0]?.[0], {
        ...result,
        provider: 'klipy',
    });
});

test('loads more with the cursor from the previous page', async () => {
    trending.mockImplementation(
        async (
            _key: string,
            options: { cursor: string | null },
        ) =>
            options.cursor === null
                ? { results: [result], nextCursor: 'next-page' }
                : {
                      results: [{ ...result, id: 'two' }],
                      nextCursor: null,
                  },
    );

    render();
    await settle();

    const fields = render();
    const loadMore = fields.find(
        (element) => element.props.children === 'Load more',
    );

    assert.ok(loadMore);
    (loadMore.props.onClick as () => void)();

    const [key, options] = trending.mock.calls.at(-1) ?? [];

    assert.equal(key, 'k');
    assert.equal(options?.cursor, 'next-page');
    assert.ok(options?.signal instanceof AbortSignal);
});

test('offers load more when a page filters to no results', async () => {
    trending.mockImplementation(
        async (_key: string, options: { cursor: string | null }) =>
            options.cursor === null
                ? { results: [], nextCursor: 'next-page' }
                : { results: [result], nextCursor: null },
    );

    render();
    await settle();

    const fields = render();

    assert.ok(
        fields.some(
            (element) =>
                element.props.children ===
                'No GIFs matched. /dev/null remains empty.',
        ),
    );
    assert.ok(
        fields.some((element) => element.props.children === 'Load more'),
    );
});

test('renders no GIF dialog without a provider key and keeps the image picker working', () => {
    const fields = elements(
        ComposerMediaButton({
            gifKey: null,
            gifOpen: false,
            onGifOpenChange: vi.fn(),
            hasGif: false,
            count: 0,
            onPickImages: vi.fn(async () => {}),
            onPickGif: vi.fn(),
        }) as ReactElement,
    );

    assert.equal(
        fields.some((element) => element.props.apiKey !== undefined),
        false,
    );
    assert.ok(field(fields, 'attach-images-button'));
});

test('opens the picker with the stored provider and key through one media trigger', () => {
    const onGifOpenChange = vi.fn();
    const fields = elements(
        ComposerMediaButton({
            gifKey: { provider: 'klipy', key: 'stored-key' },
            gifOpen: false,
            onGifOpenChange,
            hasGif: false,
            count: 0,
            onPickImages: vi.fn(async () => {}),
            onPickGif: vi.fn(),
        }) as ReactElement,
    );
    const dialog = fields.find(
        (element) => element.props.apiKey === 'stored-key',
    );

    assert.ok(dialog);
    assert.equal(dialog.props.provider, gifProviderFor('klipy'));
    assert.equal(dialog.props.open, false);
    assert.ok(field(fields, 'attach-media-button'));
    assert.ok(field(fields, 'attach-images-button'));
    assert.ok(field(fields, 'attach-gif-button'));

    const preventDefault = vi.fn();
    (
        field(fields, 'attach-gif-button').props.onSelect as (event: {
            preventDefault: () => void;
        }) => void
    )({ preventDefault });

    assert.deepEqual(onGifOpenChange.mock.calls, [[true]]);
    assert.equal(preventDefault.mock.calls.length, 0);
});
