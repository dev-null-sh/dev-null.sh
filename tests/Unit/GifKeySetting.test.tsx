/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';

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

vi.doMock('../../resources/js/hooks/use-user', () => ({
    useUser: () => ({ user: { id: 'user-1' } }),
}));

const { GifKeySetting } = await import(
    '../../resources/js/components/gif-key-setting'
);
const { readGifProviderKey, writeGifProviderKey } = await import(
    '../../resources/js/lib/gif-provider-key'
);

type Element = ReactElement<Record<string, unknown>>;

let store: Map<string, string>;

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

function render(): Element[] {
    cursor = 0;

    return elements(GifKeySetting());
}

function field(fields: Element[], testId: string): Element {
    const found = fields.find(
        (element) => element.props['data-test'] === testId,
    );

    assert.ok(found, `no element with data-test="${testId}"`);

    return found;
}

function stubFetch(result: number | 'network'): void {
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => {
            if (result === 'network') {
                throw new TypeError('fetch failed');
            }

            return new Response('{}', { status: result });
        }),
    );
}

function typeKey(fields: Element[], value: string): Element[] {
    (field(fields, 'gif-key-input').props.onChange as (event: {
        target: { value: string };
    }) => void)({ target: { value } });

    return render();
}

function selectProvider(fields: Element[], id: 'giphy' | 'klipy'): Element[] {
    (field(fields, `provider-${id}`).props.onClick as () => void)();

    return render();
}

beforeEach(() => {
    state = [];
    cursor = 0;
    store = new Map();
    vi.clearAllMocks();
    vi.unstubAllGlobals();

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
});

afterEach(() => {
    globalThis.window = originalWindow;
});

test('shows the browser-only notice', () => {
    const note = field(render(), 'gif-storage-note');

    assert.equal(
        React.Children.toArray(note.props.children as ReactNode)
            .map((child) => (typeof child === 'string' ? child : ''))
            .join('')
            .replace(/\s+/g, ' ')
            .trim(),
        'Stored in this browser only — never sent to the server. Each device needs its own key.',
    );
});

test('lists klipy first', () => {
    const labels = render()
        .filter((element) =>
            String(element.props['data-test']).startsWith('provider-'),
        )
        .map((element) => element.props.children);

    assert.deepEqual(labels, ['KLIPY', 'GIPHY']);
});

test('saves a valid key to local storage after giphy accepts it', async () => {
    stubFetch(200);

    let fields = selectProvider(render(), 'giphy');
    fields = typeKey(fields, '  key-123  ');
    await (field(fields, 'gif-connect-button').props.onClick as () => Promise<void>)();

    assert.deepEqual(store.get('gif-provider:user-1'), JSON.stringify({ provider: 'giphy', key: 'key-123' }));
    assert.deepEqual(readGifProviderKey('user-1'), { provider: 'giphy', key: 'key-123' });

    fields = render();
    assert.equal(
        React.Children.toArray(
            field(fields, 'gif-connected').props.children as ReactNode,
        )
            .map((child) => (typeof child === 'string' ? child : ''))
            .join(''),
        'Connected to GIPHY.',
    );
});

test('defaults to klipy when no provider is stored', async () => {
    stubFetch(200);

    const fields = typeKey(render(), 'key-123');
    await (field(fields, 'gif-connect-button').props.onClick as () => Promise<void>)();

    assert.deepEqual(readGifProviderKey('user-1'), { provider: 'klipy', key: 'key-123' });
});

test('saves a rate-limited key because the key itself is real', async () => {
    stubFetch(429);

    const fields = typeKey(render(), 'key-123');
    await (field(fields, 'gif-connect-button').props.onClick as () => Promise<void>)();

    assert.deepEqual(readGifProviderKey('user-1'), { provider: 'klipy', key: 'key-123' });
});

test('rejects a refused key without saving', async () => {
    stubFetch(401);

    let fields = selectProvider(render(), 'giphy');
    fields = typeKey(fields, 'key-123');
    await (field(fields, 'gif-connect-button').props.onClick as () => Promise<void>)();

    assert.equal(readGifProviderKey('user-1'), null);
    assert.equal(
        field(render(), 'gif-key-error').props.children,
        'That key was rejected by GIPHY.',
    );
});

test('names klipy when it refuses the key', async () => {
    stubFetch(401);

    const fields = typeKey(render(), 'key-123');
    await (field(fields, 'gif-connect-button').props.onClick as () => Promise<void>)();

    assert.equal(readGifProviderKey('user-1'), null);
    assert.equal(
        field(render(), 'gif-key-error').props.children,
        'That key was rejected by KLIPY.',
    );
});

test('clears the error when another provider is selected', async () => {
    stubFetch(401);

    const fields = typeKey(render(), 'key-123');
    await (field(fields, 'gif-connect-button').props.onClick as () => Promise<void>)();

    assert.ok(field(render(), 'gif-key-error'));

    (field(render(), 'provider-giphy').props.onClick as () => void)();

    assert.equal(
        render().some(
            (element) => element.props['data-test'] === 'gif-key-error',
        ),
        false,
    );
});

test('does not save when the provider cannot be reached', async () => {
    stubFetch('network');

    let fields = selectProvider(render(), 'giphy');
    fields = typeKey(fields, 'key-123');
    await (field(fields, 'gif-connect-button').props.onClick as () => Promise<void>)();

    assert.equal(readGifProviderKey('user-1'), null);
    assert.equal(
        field(render(), 'gif-key-error').props.children,
        "Couldn't reach GIPHY to verify the key.",
    );
});

test('disables connect until a key is typed', () => {
    assert.equal(field(render(), 'gif-connect-button').props.disabled, true);
});

test('does not save a whitespace-only key', async () => {
    stubFetch(200);

    const fields = typeKey(render(), '   ');
    await (field(fields, 'gif-connect-button').props.onClick as () => Promise<void>)();

    assert.equal(readGifProviderKey('user-1'), null);
});

test('rejects an overlong key before verifying it', async () => {
    const fetchMock = vi.fn(
        async () => new Response('{}', { status: 200 }),
    );
    vi.stubGlobal('fetch', fetchMock);

    const fields = typeKey(render(), 'a'.repeat(256));
    await (field(fields, 'gif-connect-button').props.onClick as () => Promise<void>)();

    assert.equal(fetchMock.mock.calls.length, 0);
    assert.equal(readGifProviderKey('user-1'), null);
    assert.equal(
        field(render(), 'gif-key-error').props.children,
        'That key is too long.',
    );
});

test('keeps the typed key and edit mode when the browser refuses to save', async () => {
    stubFetch(200);

    writeGifProviderKey('user-1', { provider: 'giphy', key: 'old' });

    let fields = render();
    (field(fields, 'change-provider-button').props.onClick as () => void)();
    fields = render();

    globalThis.window.localStorage.setItem = () => undefined;

    (field(fields, 'gif-key-input').props.onChange as (event: {
        target: { value: string };
    }) => void)({ target: { value: 'new-key' } });
    fields = render();

    await (field(fields, 'gif-connect-button').props.onClick as () => Promise<void>)();

    fields = render();

    assert.equal(field(fields, 'gif-key-input').props.value, 'new-key');
    assert.equal(
        field(fields, 'gif-key-error').props.children,
        "Couldn't save the key in this browser.",
    );
    assert.deepEqual(readGifProviderKey('user-1'), {
        provider: 'giphy',
        key: 'old',
    });
});

test('shows the connected provider from local storage', () => {
    writeGifProviderKey('user-1', { provider: 'klipy', key: 'k' });

    const fields = render();

    assert.equal(
        React.Children.toArray(
            field(fields, 'gif-connected').props.children as ReactNode,
        )
            .map((child) => (typeof child === 'string' ? child : ''))
            .join(''),
        'Connected to KLIPY.',
    );
});

test('describes the stored provider instead of the default one', () => {
    let fields = render();

    assert.ok(
        fields.some(
            (element) => element.props.href === 'https://partner.klipy.com',
        ),
    );

    writeGifProviderKey('user-1', { provider: 'giphy', key: 'k' });

    fields = render();

    const description = fields.find(
        (element) => element.props.id === 'gif-key-description',
    );

    assert.ok(description);
    assert.ok(
        React.Children.toArray(description.props.children as ReactNode)
            .map((child) => (typeof child === 'string' ? child : ''))
            .join('')
            .includes('GIPHY'),
    );
    assert.ok(
        fields.some(
            (element) => element.props.href === 'https://developers.giphy.com',
        ),
    );
});

test('never echoes the stored key in the connected view', () => {
    writeGifProviderKey('user-1', { provider: 'giphy', key: 'super-secret-key' });

    const fields = render();
    assert.ok(field(fields, 'gif-connected'));

    const text = fields
        .flatMap((element) =>
            React.Children.toArray(element.props.children as ReactNode),
        )
        .map((child) => (typeof child === 'string' ? child : ''))
        .join('');

    assert.equal(text.includes('super-secret-key'), false);
});

test('removes the stored key', () => {
    writeGifProviderKey('user-1', { provider: 'giphy', key: 'k' });

    const fields = render();
    (field(fields, 'gif-remove-button').props.onClick as () => void)();

    assert.equal(readGifProviderKey('user-1'), null);
    assert.ok(field(render(), 'gif-key-input'));
});

test('replaces the connected provider when another is chosen', async () => {
    stubFetch(200);

    writeGifProviderKey('user-1', { provider: 'giphy', key: 'old' });

    let fields = render();
    (field(fields, 'change-provider-button').props.onClick as () => void)();

    fields = render();
    (field(fields, 'provider-klipy').props.onClick as () => void)();

    fields = render();
    (field(fields, 'gif-key-input').props.onChange as (event: {
        target: { value: string };
    }) => void)({ target: { value: 'klipy-key' } });

    fields = render();
    await (field(fields, 'gif-connect-button').props.onClick as () => Promise<void>)();

    assert.deepEqual(readGifProviderKey('user-1'), { provider: 'klipy', key: 'klipy-key' });

    fields = render();
    (field(fields, 'change-provider-button').props.onClick as () => void)();
    fields = render();

    assert.equal(field(fields, 'gif-key-input').props.value, '');
});
