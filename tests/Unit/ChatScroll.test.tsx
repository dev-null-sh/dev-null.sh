/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterEach, test, vi } from 'vite-plus/test';
import type { ChatTarget } from '../../resources/js/lib/chat';
import type { Message, User } from '../../resources/js/types';

let slots: unknown[] = [];
let cursor = 0;
let layouts: (() => unknown)[] = [];
let effects: (() => unknown)[] = [];
let thread: Record<string, unknown>;
vi.doMock('react', () => ({
    ...React,
    useRef: (initial: unknown) => {
        const index = cursor++;
        return (slots[index] ??= { current: initial });
    },
    useState: (initial: unknown) => {
        const index = cursor++;
        if (!(index in slots)) slots[index] = initial;
        return [
            slots[index],
            (value: unknown) => {
                slots[index] = value;
            },
        ];
    },
    useMemo: (compute: () => unknown) => compute(),
    useCallback: (callback: unknown) => callback,
    useId: () => 'id',
    useEffect: (effect: () => unknown) => {
        effects.push(effect);
    },
    useLayoutEffect: (effect: () => unknown, dependencies: unknown[]) => {
        const index = cursor++;
        const previous = slots[index] as unknown[] | undefined;
        if (!previous || dependencies.some((value, i) => value !== previous[i]))
            layouts.push(effect);
        slots[index] = dependencies;
    },
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({ props: { auth: { dateTime: {} } } }),
}));
vi.doMock('../../resources/js/hooks/use-chat', () => ({
    useThread: () => thread,
    useChat: () => ({ mutePreferences: {} }),
}));
vi.doMock('../../resources/js/hooks/use-stash', () => ({
    useStash: () => ({}),
    moveCaretToEnd: vi.fn(),
}));
vi.doMock('../../resources/js/hooks/use-image-attachments', () => ({
    useImageAttachments: () => ({}),
}));
vi.doMock('../../resources/js/hooks/use-link-card', () => ({
    useLinkCard: () => ({}),
}));
const chatStore = await import('../../resources/js/lib/chat');
const { ConversationThread } =
    await import('../../resources/js/components/chat/conversation-thread');
const { MessageImages, MessageText } =
    await import('../../resources/js/components/chat/message-parts');
type Element = ReactElement<Record<string, unknown>>;
function elements(node: ReactNode): Element[] {
    if (!React.isValidElement(node)) return [];
    const element = node as Element;
    return [
        element,
        ...React.Children.toArray(element.props.children as ReactNode).flatMap(
            elements,
        ),
    ];
}
afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

function setup(
    reducedMotion = false,
    initialScrollTop = 0,
    kind: 'direct' | 'org' = 'org',
) {
    slots = [];
    thread = {
        isLoaded: true,
        messages: [
            {
                id: 1,
                client_id: 'photo',
                user_id: 'partner',
                created_at: new Date().toISOString(),
            },
        ],
        pending: [],
    };
    vi.stubGlobal('window', {
        matchMedia: () => ({ matches: reducedMotion }),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    });
    vi.stubGlobal('document', {
        hidden: true,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    });
    let onResize = () => {};
    vi.stubGlobal(
        'ResizeObserver',
        class {
            constructor(callback: () => void) {
                onResize = callback;
            }
            observe() {}
            disconnect() {}
        },
    );
    vi.stubGlobal('CSS', { escape: (value: string) => value });
    let scrollTop = initialScrollTop;
    let rowTop = 100;
    const writes: number[] = [];
    const scrollTo = vi.fn();
    const row = {
        dataset: { scrollAnchor: 'photo' },
        getBoundingClientRect: () => ({
            top: rowTop - scrollTop,
            bottom: rowTop - scrollTop + 320,
        }),
    };
    const scroller = {
        get scrollTop() {
            return scrollTop;
        },
        set scrollTop(value: number) {
            scrollTop = value;
            writes.push(value);
        },
        getBoundingClientRect: () => ({ top: 0 }),
        querySelectorAll: () => [row],
        querySelector: (_selector: string): unknown => row,
        scrollTo,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    };
    const user = { id: 'me' } as User;
    const target: ChatTarget =
        kind === 'direct'
            ? { kind, partner: { id: 'partner', handle: 'demo' } as User }
            : { kind, org: { id: 'org', name: 'Demo' } };
    const render = () => {
        cursor = 0;
        layouts = [];
        effects = [];
        const nodes = elements(
            ConversationThread({ target, user, header: null }),
        );
        const scroll = nodes.find(
            (node) => node.props['data-test'] === 'conversation-messages',
        )!;
        (scroll.props.ref as React.RefObject<unknown>).current = scroller;
        (
            nodes.find((node) => node.type === 'ol')!.props
                .ref as React.RefObject<unknown>
        ).current = {};
        for (const effect of layouts) effect();
        for (const effect of effects) effect();
        return {
            nodes,
            scroll,
            reply: nodes.find(
                (node) => typeof node.props.onReply === 'function',
            )!.props.onReply as (reply: unknown) => void,
            composer: nodes.find(
                (node) => typeof node.props.onSend === 'function',
            )!,
        };
    };
    const initial = render();
    const send = () => {
        (initial.composer.props.onSend as () => void)();
        rowTop = -20;
        thread.pending = [
            {
                client_id: 'sent',
                created_at: new Date().toISOString(),
                status: 'staged',
            },
        ];
        render();
    };
    return {
        render,
        send,
        scroller,
        scrollTo,
        writes,
        nodes: () => render().nodes,
        reply: initial.reply,
        resize: () => {
            rowTop += 150;
            onResize();
        },
    };
}

test('reveals a sent reply from the current reading position instead of snapping to the bottom', () => {
    const chat = setup(false, -150);
    chat.send();
    assert.equal(chat.scroller.scrollTop, -270);
    assert.deepEqual(chat.scrollTo.mock.calls, [
        [{ top: 0, behavior: 'smooth' }],
    ]);
});

test('respects reduced motion when revealing a sent reply', () => {
    const chat = setup(true, -150);
    chat.send();
    assert.deepEqual(chat.scrollTo.mock.calls, [
        [{ top: 0, behavior: 'instant' }],
    ]);
});

test('server confirmation does not cut an ongoing send scroll short', () => {
    const chat = setup(false, -150);
    chat.send();
    chat.scroller.scrollTop = -30;
    (chat.render().scroll.props.onScroll as () => void)();
    const writes = chat.writes.length;
    thread.pending = [];
    thread.messages = [
        {
            id: 1,
            client_id: 'sent',
            user_id: 'me',
            created_at: new Date().toISOString(),
        } as Message,
    ];
    chat.render();
    assert.equal(chat.writes.length, writes);
    assert.equal(chat.scrollTo.mock.calls.length, 1);
});

test('keeps following the latest messages while a reply is open at the bottom', () => {
    const chat = setup();
    chat.reply({ id: 1, user_id: 'partner', body: '', images_count: 1 });
    thread.messages = [
        {
            id: 2,
            client_id: 'incoming',
            user_id: 'partner',
            created_at: new Date().toISOString(),
        },
    ];
    chat.render();
    assert.equal(chat.scroller.scrollTop, 0);
    assert.deepEqual(chat.writes, [0]);
});

test('resizing during a send does not replace the smooth reveal with a reading-anchor correction', () => {
    const chat = setup(false, -150);
    chat.send();
    chat.scroller.scrollTop = -90;
    (chat.render().scroll.props.onScroll as () => void)();
    const writes = chat.writes.length;
    chat.resize();
    assert.equal(chat.writes.length, writes);
    assert.equal(chat.scrollTo.mock.calls.length, 1);
});

test('keeps a sent message at the bottom without animating the thread', () => {
    const chat = setup();
    chat.send();
    assert.equal(chat.scroller.scrollTop, 0);
    assert.equal(chat.scrollTo.mock.calls.length, 0);
    const writes = chat.writes.length;
    thread.pending = [];
    thread.messages = [
        {
            id: 2,
            client_id: 'sent',
            user_id: 'me',
            created_at: new Date().toISOString(),
        } as Message,
    ];
    chat.render();
    assert.equal(chat.scroller.scrollTop, 0);
    assert.deepEqual(chat.writes.slice(writes), [0]);
    assert.equal(chat.scrollTo.mock.calls.length, 0);
});

test('settles a send near the latest message without a scroll animation', () => {
    const chat = setup(false, -20);
    chat.send();
    assert.equal(chat.scroller.scrollTop, 0);
    assert.equal(chat.scrollTo.mock.calls.length, 0);
});

test.each([
    ['direct', false, false],
    ['direct', true, false],
    ['org', false, false],
    ['org', true, false],
    ['direct', false, true],
    ['direct', true, true],
    ['org', false, true],
    ['org', true, true],
] as const)(
    'jumps to the quoted content in a %s chat (paginated: %s, caption: %s)',
    async (kind, paginated, caption) => {
        const chat = setup(false, 0, kind);
        thread.messages = [
            {
                id: 1,
                client_id: 'photo',
                user_id: 'partner',
                body: caption ? 'Release dashboard ready for review.' : '',
                created_at: new Date().toISOString(),
                images: [
                    {
                        id: 'photo',
                        url: '/photo.webp',
                        description: 'Build dashboard',
                    },
                ],
            },
        ];
        const group = chat
            .nodes()
            .find(
                (node) =>
                    (node.props.item as { kind?: string } | undefined)?.kind ===
                    'group',
            )!;
        const renderGroup = (
            group.type as unknown as {
                type: (props: typeof group.props) => ReactNode;
            }
        ).type;
        const bubble = elements(renderGroup(group.props)).find(
            (node) => node.props.bubble !== undefined,
        )!;
        const renderBubble = bubble.type as (
            props: typeof bubble.props,
        ) => ReactNode;
        const targets = elements(renderBubble(bubble.props))
            .filter((node) => node.props['data-message-id'] === 1)
            .map((node) => ({
                node,
                dataset: {} as Record<string, string>,
                scrollIntoView: vi.fn(),
            }));
        const load = vi.spyOn(chatStore, 'loadMessage').mockResolvedValue(true);
        chat.scroller.querySelector = (selector: string) =>
            !paginated || load.mock.calls.length > 0
                ? (targets.find(
                      ({ node }) =>
                          selector ===
                          `[data-message-id="${String(node.props['data-message-id'])}"]`,
                  ) ?? null)
                : null;
        vi.stubGlobal('requestAnimationFrame', (callback: () => void) =>
            callback(),
        );
        const timers: (() => void)[] = [];
        Object.assign(window, {
            setTimeout: (callback: () => void) => timers.push(callback),
        });

        await (group.props.onJump as (id: number) => Promise<void>)(1);

        const original = targets.find(({ node }) =>
            elements(node).some(
                (child) =>
                    child.type === (caption ? MessageText : MessageImages),
            ),
        )!;
        assert.deepEqual(original.scrollIntoView.mock.calls, [
            [{ block: 'center', behavior: 'instant' }],
        ]);
        assert.equal(original.dataset.flash, '');
        assert.equal(load.mock.calls.length, paginated ? 1 : 0);
        timers.forEach((callback) => callback());
        assert.equal(original.dataset.flash, undefined);
    },
);
