import assert from 'node:assert/strict';
import type { ReloadOptions } from '@inertiajs/core';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterEach, test, vi } from 'vite-plus/test';
import { connectRealtime } from '../../resources/js/lib/realtime';
import { fakeConnection } from './FakeRealtime';
import type {
    Commit,
    CursorPaginated,
    Log,
    User,
} from '../../resources/js/types';

let slots: unknown[];
let cursor: number;
let effects: (() => unknown)[];
let layouts: (() => unknown)[];
const listeners = new Map<string, (event: unknown) => void>();
let viewer: User | null = null;
const reload = vi.fn();
let pollOptions: () => ReloadOptions;
let replaceCommits: (value: (old: unknown, props: unknown) => unknown) => void;
vi.doMock('react', () => ({
    ...React,
    useRef: (initial: unknown) => (slots[cursor++] ??= { current: initial }),
    useState: (initial: unknown) => {
        const index = cursor++;
        if (!(index in slots)) slots[index] = initial;
        return [
            slots[index],
            (value: unknown) => {
                slots[index] =
                    typeof value === 'function' ? value(slots[index]) : value;
            },
        ];
    },
    useCallback: (callback: unknown, dependencies: unknown[]) => {
        const index = cursor++;
        const previous = slots[index] as
            | { dependencies: unknown[]; callback: unknown }
            | undefined;
        if (
            !previous ||
            dependencies.some(
                (value, i) => !Object.is(value, previous.dependencies[i]),
            )
        )
            slots[index] = { callback, dependencies };
        return (slots[index] as { callback: unknown }).callback;
    },
    useEffect: (effect: () => unknown, dependencies?: unknown[]) =>
        scheduleEffect(effects, effect, dependencies),
    useLayoutEffect: (effect: () => unknown, dependencies?: unknown[]) =>
        scheduleEffect(layouts, effect, dependencies),
    useEffectEvent: (callback: (...args: unknown[]) => unknown) => {
        const index = cursor++;
        const cell = (slots[index] ??= { callback }) as { callback: typeof callback; handle?: typeof callback };
        cell.callback = callback;
        cell.handle ??= (...args: unknown[]) => cell.callback(...args);
        return cell.handle;
    },
    useSyncExternalStore: (_subscribe: unknown, read: () => unknown) => read(),
}));
function scheduleEffect(
    queue: (() => unknown)[],
    effect: () => unknown,
    dependencies?: unknown[],
) {
    const index = cursor++;
    const previous = slots[index] as
        | { dependencies?: unknown[]; cleanup?: () => void }
        | undefined;
    if (
        !previous ||
        !dependencies ||
        dependencies.some(
            (value, i) => !Object.is(value, previous.dependencies?.[i]),
        )
    ) {
        queue.push(() => {
            previous?.cleanup?.();
            slots[index] = { dependencies, cleanup: effect() };
        });
    }
}
vi.doMock('@inertiajs/react', () => ({
    Head: () => null,
    Link: () => null,
    usePage: () => ({ props: { auth: { organizations: [], user: viewer } } }),
    usePoll: (_interval: number, options: () => ReloadOptions) => {
        pollOptions = options;
        return { start: vi.fn(), stop: vi.fn() };
    },
    router: {
        on: (name: string, listener: (event: unknown) => void) => {
            listeners.set(name, listener);
            return () => listeners.delete(name);
        },
        reload,
        replaceProp: (
            _name: string,
            value: (old: unknown, props: unknown) => unknown,
        ) => replaceCommits(value),
        flushAll: vi.fn(),
    },
}));
vi.doMock('../../resources/js/components/commit-composer-dialog', () => ({
    useCommitDialogClosedCount: () => 0,
}));
vi.doMock('../../resources/js/hooks/use-composer-visibility', () => ({
    useComposerVisibility: vi.fn(),
}));
vi.doMock('../../resources/js/hooks/use-lane-swipe', () => ({
    useLaneSwipe: vi.fn(),
}));
vi.doMock('../../resources/js/hooks/use-last-visit', () => ({
    useLastVisit: () => null,
}));
const { default: Show } = await import('../../resources/js/pages/log/show');
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
    slots?.forEach((slot) => (slot as { cleanup?: () => void })?.cleanup?.());
    connectRealtime(null);
    vi.useRealTimers();
    vi.unstubAllGlobals();
    viewer = null;
});

function insertAt(
    offset: number,
    reduced = false,
    authors: User[] = [],
    log: Log = 'latest',
    total = 3,
    focused = false,
    initialOnly = false,
) {
    if (focused) viewer = { id: 'reader' } as User;
    slots = [];
    listeners.clear();
    reload.mockClear();
    vi.useFakeTimers();
    let scrollY = offset;
    let rowPosition = offset + 80;
    const animate = vi.fn();
    const input = {
        isConnected: true,
        getBoundingClientRect: () => ({
            top: 60 - scrollY,
            bottom: 100 - scrollY,
        }),
        closest: () => input,
    };
    const composer = { contains: (element: unknown) => element === input };
    const existing = {
        dataset: { commit: 'existing' },
        isConnected: true,
        getBoundingClientRect: () => ({
            top: rowPosition - scrollY,
            bottom: rowPosition - scrollY + 120,
            height: 120,
        }),
    };
    const fresh = Array.from(
        { length: total },
        (_, index) => `new-${index}`,
    ).map((id) => ({
        dataset: { commit: id },
        getBoundingClientRect: () => ({ height: 120 }),
        animate,
        removeAttribute: vi.fn(),
    }));
    let rows: unknown[] = [existing];
    const scrollListeners: (() => void)[] = [];
    const focusListeners: (() => void)[] = [];
    const resizeListeners: (() => void)[] = [];
    const mutationListeners: (() => void)[] = [];
    const visibilityListeners: (() => void)[] = [];
    const scrollTopListeners: (() => void)[] = [];
    const intersections: {
        callback: (entries: unknown[]) => void;
        disconnected: boolean;
        observed: Set<unknown>;
    }[] = [];
    vi.stubGlobal('window', {
        innerHeight: 900,
        get scrollY() {
            return scrollY;
        },
        scrollTo: ({ top }: { top: number }) => {
            scrollY = top;
        },
        scrollBy: (_x: number, y: number) => {
            scrollY += y;
        },
        matchMedia: () => ({ matches: reduced }),
        addEventListener: (name: string, listener: () => void) => {
            if (name === 'log:scroll-top') scrollTopListeners.push(listener);
            if (name === 'scroll') scrollListeners.push(listener);
            if (name === 'focusin' || name === 'focusout')
                focusListeners.push(listener);
        },
        dispatchEvent: (event: Event) => {
            if (event.type === 'log:scroll-top') scrollTopListeners.forEach((listener) => listener());
            return true;
        },
        removeEventListener: (name: string, listener: () => void) => {
            const callbacks = name === 'log:scroll-top' ? scrollTopListeners :
                name === 'scroll' ? scrollListeners : focusListeners;
            const index = callbacks.indexOf(listener);
            if (index !== -1) callbacks.splice(index, 1);
        },
    });
    vi.stubGlobal('requestAnimationFrame', () => 1);
    vi.stubGlobal('cancelAnimationFrame', () => {});
    vi.stubGlobal('document', {
        activeElement: focused ? input : null,
        visibilityState: 'visible',
        addEventListener: (_name: string, listener: () => void) =>
            visibilityListeners.push(listener),
        removeEventListener: (_name: string, listener: () => void) => {
            const i = visibilityListeners.indexOf(listener);
            if (i !== -1) visibilityListeners.splice(i, 1);
        },
        documentElement: { style: { overflowAnchor: '' } },
    });
    vi.stubGlobal(
        'MutationObserver',
        class {
            constructor(callback: () => void) {
                mutationListeners.push(callback);
            }
            observe() {}
            disconnect() {}
        },
    );
    vi.stubGlobal(
        'ResizeObserver',
        class {
            constructor(callback: () => void) {
                resizeListeners.push(callback);
            }
            observe() {}
            disconnect() {}
        },
    );
    vi.stubGlobal(
        'IntersectionObserver',
        class {
            state: {
                callback: (entries: unknown[]) => void;
                disconnected: boolean;
                observed: Set<unknown>;
            };
            constructor(callback: (entries: unknown[]) => void) {
                this.state = {
                    callback,
                    disconnected: false,
                    observed: new Set(),
                };
                intersections.push(this.state);
            }
            observe(row: unknown) {
                this.state.disconnected = false;
                this.state.observed.add(row);
            }
            disconnect() {
                this.state.disconnected = true;
                this.state.observed.clear();
            }
        },
    );
    const commits = (ids: string[]) =>
        ({ data: ids.map((id) => ({ id })) }) as CursorPaginated<Commit>;
    const render = (commits: CursorPaginated<Commit>) => {
        cursor = 0;
        effects = [];
        layouts = [];
        const nodes = elements(
            Show({
                log,
                commits,
                isRanked: false,
                suggestions: [],
            }),
        );
        for (const node of nodes) {
            if (typeof node.props.ref === 'function') {
                node.props.ref(composer);
                continue;
            }
            if (typeof node.props.className !== 'string' || !node.props.ref)
                continue;
            const ref = node.props.ref as React.RefObject<unknown>;
            if (node.props.className.includes('log-header'))
                ref.current = {
                    getBoundingClientRect: () => ({ bottom: 52, height: 52 }),
                };
            if (node.props.className.includes('log-feed'))
                ref.current = { querySelectorAll: () => rows };
        }
        for (const effect of layouts) effect();
        for (const effect of effects) effect();
        return nodes;
    };
    const previous = commits(['existing']);
    const pending = render(previous);
    const poll = (count = total, removedCommitIds: string[] = []) =>
        pollOptions().onSuccess!({
            props: { newCommitsCount: count, removedCommitIds },
        } as never);
    if (!initialOnly) poll();
    const fetching = render(previous);
    const incoming = {
        data: initialOnly
            ? previous.data
            : [
                  ...fresh.map((row, index) => {
                      const user = authors.length
                          ? authors[index % authors.length]
                          : { id: 'author', name: 'Author', avatar_url: null };
                      return { id: row.dataset.commit, author: user };
                  }),
                  ...previous.data,
              ],
    } as CursorPaginated<Commit>;
    if (!initialOnly)
        listeners.get('beforeUpdate')!({
            detail: {
                page: {
                    component: 'log/show',
                    props: { commits: incoming },
                    scrollProps: { commits: { reset: true } },
                },
            },
        });
    const insertionAnchoring = document.documentElement.style.overflowAnchor;
    rowPosition += initialOnly ? 0 : total * 120;
    rows = initialOnly ? [existing] : [...fresh, existing];
    render(incoming);
    let current = incoming;
    replaceCommits = (value) => {
        const next = value(current, { log }) as CursorPaginated<Commit>;
        const removed = current.data.length - next.data.length;
        rows = rows.filter((row) =>
            next.data.some(
                (c) => c.id === (row as typeof existing).dataset.commit,
            ),
        );
        rowPosition -= removed * 120;
        current = next;
        render(current);
    };
    const finish = (success = true, delivered = incoming) => {
        const request = reload.mock.calls.at(-1)![0];
        if (success) request.onSuccess?.({ props: { commits: delivered } });
        request.onFinish();
        return render(incoming);
    };
    return {
        incoming,
        insertionAnchoring,
        data: () => current.data,
        request: () => pollOptions(),
        readingTop: () => existing.getBoundingClientRect().top,
        composerTop: () => input.getBoundingClientRect().top,
        blur: () => {
            Object.defineProperty(document, 'activeElement', {
                value: null,
                configurable: true,
            });
            focusListeners.forEach((listener) => listener());
        },
        refreshCounts: () =>
            listeners.get('beforeUpdate')!({
                detail: {
                    page: {
                        component: 'log/show',
                        props: { commits: current },
                        scrollProps: { commits: { reset: true } },
                    },
                },
            }),
        mutateAbove: (height: number) => {
            rowPosition += height;
            mutationListeners.forEach((listener) => listener());
        },
        resizeAbove: (height: number) => {
            rowPosition += height;
            resizeListeners.forEach((listener) => listener());
        },
        render: () => render(current),
        currentScroll: () => scrollY,
        progressScroll: (top: number, height: number) => {
            scrollY = top;
            rowPosition += height;
            mutationListeners.forEach((listener) => listener());
            resizeListeners.forEach((listener) => listener());
            scrollListeners.forEach((listener) => listener());
            render(current);
        },
        paginate: () => {
            current = {
                ...current,
                data: [...current.data, { id: 'older' } as Commit],
            };
            return render(current);
        },
        switchLog: (next: Log) => {
            log = next;
            return render(current);
        },
        deliver: (data: Commit[]) => {
            current = { ...current, data };
            render(current);
        },
        scrollTo: (top: number) => {
            scrollY = top;
            scrollListeners.forEach((listener) => listener());
            render(current);
        },
        visibility: (state: 'hidden' | 'visible') => {
            Object.defineProperty(document, 'visibilityState', {
                value: state,
                configurable: true,
            });
            visibilityListeners.forEach((listener) => listener());
            render(current);
        },
        sight: (index: number, ratio = 1) => {
            for (const observer of intersections) {
                if (
                    !observer.disconnected &&
                    observer.observed.has(fresh[index])
                )
                    observer.callback([
                        {
                            target: fresh[index],
                            isIntersecting: ratio > 0,
                            intersectionRatio: ratio,
                        },
                    ]);
            }
            render(current);
        },
        poll,
        pending,
        fetching,
        animate,
        top: existing.getBoundingClientRect().top,
        scrollY,
        finish,
    };
}

for (const offset of [1, 20, 39, 40, 80, 500]) {
    test(`keeps the reading row still when commits arrive ${offset}px from the top`, () => {
        const result = insertAt(offset);

        assert.equal(result.top, 80);
        assert.equal(result.animate.mock.calls.length, 0);
    });
}

for (const reduced of [false, true]) {
    test(`preserves the reading row at the top with reduced motion ${reduced}`, () => {
        const result = insertAt(0, reduced);
        assert.equal(result.top, 80);
        assert.equal(result.scrollY, 360);
        assert.equal(result.animate.mock.calls.length, 0);
    });
}

test('keeps the loaded control while new commits are above a shallowly scrolled reader', () => {
    const result = insertAt(20);

    result.finish();

    assert.equal(vi.getTimerCount(), 0);
});

for (const log of ['following', 'latest'] as const) {
    test(`silently loads ${log} and shows one prompt only after success while scrolled`, () => {
        const result = insertAt(500, false, [], log);
        assert.equal(
            result.pending.some((node) => node.type === 'button'),
            false,
        );
        assert.equal(
            result.fetching.some((node) => node.type === 'button'),
            false,
        );
        const button = result.finish().find((node) => node.type === 'button')!;
        assert.equal(
            button.props['aria-label'],
            '3 new commits above. Scroll to top',
        );
        assert.equal(button.props.disabled, undefined);
        const requests = reload.mock.calls.length;
        (button.props.onClick as () => void)();
        assert.equal(reload.mock.calls.length, requests);
    });
    test(`offers the arrivals inserted above the reader who started at the top of ${log}`, () => {
        const result = insertAt(0, false, [], log);
        assert.equal(
            result.finish().some((node) => node.type === 'button'),
            true,
        );
    });
    test(`does not announce an unsuccessful ${log} reload`, () => {
        const result = insertAt(500, false, [], log);
        assert.equal(
            result.finish(false).some((node) => node.type === 'button'),
            false,
        );
    });
}

for (const [total, label] of [
    [1, undefined],
    [2, undefined],
    [3, undefined],
    [4, '+1'],
    [7, '+4'],
    [102, '+99'],
    [1000, '99+'],
] as const) {
    test(`previews three loaded authors and caps overflow for ${total} authors`, () => {
        const authors = Array.from({ length: total }, (_, index) => ({
            id: `author-${index}`,
            name: `Author ${index}`,
            avatar_url: null,
        })) as User[];
        const result = insertAt(500, false, authors, 'latest', total);
        const nodes = result.finish();
        assert.equal(
            nodes.filter((node) => node.props.author !== undefined).length,
            Math.min(total, 3),
        );
        const badge = nodes.find(
            (node) => node.props.title === `${total - 3} more authors`,
        );
        assert.equal(Boolean(badge), label !== undefined);
        if (badge) assert.equal(badge.props.children, label);
    });
}

test('previews distinct authors from the loaded commits', () => {
    const author = {
        id: 'author',
        name: 'Demo author',
        avatar_url: null,
    } as User;
    const nodes = insertAt(500, false, [author]).finish();
    const previews = nodes.filter((node) => node.props.author !== undefined);
    assert.equal(previews.length, 1);
    assert.equal((previews[0].props.author as User).id, author.id);
    assert.equal(
        nodes.find((node) => node.type === 'button')!.props['aria-label'],
        '3 new commits above. Scroll to top',
    );
});

test('does not overlap loads and retries a failed load on the next count poll', () => {
    const result = insertAt(500);
    assert.equal(reload.mock.calls.length, 1);
    result.poll();
    assert.equal(reload.mock.calls.length, 1);
    result.finish(false);
    result.poll();
    assert.equal(reload.mock.calls.length, 2);
});

test('only announces commits actually delivered, excluding the viewer', () => {
    viewer = { id: 'viewer', name: 'Viewer', avatar_url: null } as User;
    const result = insertAt(500, false, [viewer]);
    assert.equal(
        result.finish().some((node) => node.type === 'button'),
        false,
    );
});

test('does not announce a successful response with no new commits', () => {
    const result = insertAt(500);
    const nodes = result.finish(true, {
        data: [{ id: 'existing' }],
    } as CursorPaginated<Commit>);
    assert.equal(
        nodes.some((node) => node.type === 'button'),
        false,
    );
});

test('stays silent if the reader returns to the top before loading finishes', () => {
    const result = insertAt(500);
    result.scrollTo(0);
    assert.equal(
        result.finish().some((node) => node.type === 'button'),
        false,
    );
});

test('starts fading immediately at the top and removes the prompt after the fade', () => {
    const result = insertAt(500);
    result.finish();
    result.scrollTo(0);
    const fading = result.render().find((node) => node.type === 'button')!;
    assert.match(fading.props.className as string, /\bopacity-0\b/);
    vi.advanceTimersByTime(449);
    assert.equal(
        result.render().some((node) => node.type === 'button'),
        true,
    );
    vi.advanceTimersByTime(1);
    assert.equal(
        result.render().some((node) => node.type === 'button'),
        false,
    );
});

test('keeps the prompt until the newest arriving row is visible', () => {
    const result = insertAt(500);
    result.finish();
    result.sight(1);
    vi.advanceTimersByTime(450);
    assert.equal(
        result.render().some((node) => node.type === 'button'),
        true,
    );
    result.sight(0);
    vi.advanceTimersByTime(450);
    assert.equal(
        result.render().some((node) => node.type === 'button'),
        false,
    );
});

test('keeps the announcement when less than 60 percent of the newest row is visible', () => {
    const result = insertAt(80);
    result.finish();
    result.sight(0, 0.31);
    vi.advanceTimersByTime(8000);
    assert.equal(
        result.render().some((node) => node.type === 'button'),
        true,
    );
    result.sight(0, 0.6);
    vi.advanceTimersByTime(450);
    assert.equal(
        result.render().some((node) => node.type === 'button'),
        false,
    );
});

test('keeps background arrivals announced until they are seen in the visible tab', () => {
    const result = insertAt(80);
    result.visibility('hidden');
    result.finish();
    result.sight(0);
    vi.advanceTimersByTime(8000);
    assert.equal(
        result.render().some((node) => node.type === 'button'),
        true,
    );
    result.visibility('visible');
    result.sight(0, 0.31);
    vi.advanceTimersByTime(450);
    assert.equal(
        result.render().some((node) => node.type === 'button'),
        true,
    );
    result.sight(0);
    vi.advanceTimersByTime(450);
    assert.equal(
        result.render().some((node) => node.type === 'button'),
        false,
    );
});

test('retains background arrivals when a focused composer keeps the viewport at the top', () => {
    const result = insertAt(0, false, [], 'latest', 1, true);
    result.visibility('hidden');
    result.finish();
    assert.equal(
        result.render().some((node) => node.type === 'button'),
        true,
    );
    result.scrollTo(0);
    vi.advanceTimersByTime(450);
    assert.equal(
        result.render().some((node) => node.type === 'button'),
        true,
    );
    result.visibility('visible');
    vi.advanceTimersByTime(450);
    assert.equal(
        result.render().some((node) => node.type === 'button'),
        false,
    );
});

test('still dismisses on seeing the newest commit after older highlights expire', () => {
    const result = insertAt(500);
    result.finish();
    result.sight(1);
    vi.advanceTimersByTime(7000);
    result.sight(0);
    vi.advanceTimersByTime(450);
    assert.equal(
        result.render().some((node) => node.type === 'button'),
        false,
    );
});

test('keeps notice dismissal active after highlights settle and pagination appends rows', () => {
    const result = insertAt(500);
    result.finish();
    result.sight(1);
    vi.advanceTimersByTime(7000);
    result.paginate();
    result.sight(0);
    vi.advanceTimersByTime(450);
    assert.equal(
        result.render().some((node) => node.type === 'button'),
        false,
    );
});

for (const log of ['following', 'latest'] as const) {
    test(`reconciles deleted announcements without new arrivals in ${log}`, () => {
        const authors = Array.from({ length: 4 }, (_, i) => ({
            id: `author-${i}`,
            name: `Author ${i}`,
            avatar_url: null,
        })) as User[];
        const result = insertAt(500, false, authors, log, 4);
        result.finish();
        const requests = reload.mock.calls.length;
        assert.deepEqual(
            (result.request().data as { pending: string[] }).pending,
            ['new-0', 'new-1', 'new-2', 'new-3'],
        );
        const top = result.readingTop();
        result.poll(0, ['new-0']);
        assert.equal(result.readingTop(), top);
        const nodes = result.render();
        assert.equal(
            nodes.find((n) => n.type === 'button')!.props['aria-label'],
            '3 new commits above. Scroll to top',
        );
        assert.deepEqual(
            nodes
                .filter((n) => n.props.author)
                .map((n) => (n.props.author as User).id),
            ['author-1', 'author-2', 'author-3'],
        );
        assert.equal(
            result.data().some((c) => c.id === 'new-0'),
            false,
        );
        assert.equal(reload.mock.calls.length, requests);
        result.sight(1);
        vi.advanceTimersByTime(450);
        assert.equal(
            result.render().some((n) => n.type === 'button'),
            false,
        );
    });
    test(`fades out after all pending commits are deleted in ${log}`, () => {
        const result = insertAt(500, false, [], log);
        const before = result.finish();
        result.poll(0, ['new-0', 'new-1', 'new-2']);
        const fading = result.render();
        assert.match(
            fading.find((n) => n.type === 'button')!.props.className as string,
            /opacity-0/,
        );
        assert.equal(
            fading.filter((n) => n.props.author).length,
            before.filter((n) => n.props.author).length,
        );
        vi.advanceTimersByTime(450);
        assert.equal(
            result.render().some((n) => n.type === 'button'),
            false,
        );
        assert.deepEqual(
            result.data().map((c) => c.id),
            ['existing'],
        );
    });
}

test('reconciles removals and new arrivals in the same polling cycle', () => {
    const result = insertAt(500);
    result.finish();
    result.poll(2, ['new-0']);
    const request = reload.mock.calls.at(-1)![0];
    const delivered = {
        data: [
            {
                id: 'next-0',
                author: { id: 'next-author' },
            },
            {
                id: 'next-1',
                author: { id: 'next-author' },
            },
            ...result.data(),
        ],
    } as CursorPaginated<Commit>;
    request.onSuccess({ props: { commits: delivered } });
    request.onFinish();
    const button = result.render().find((n) => n.type === 'button')!;
    assert.equal(
        button.props['aria-label'],
        '4 new commits above. Scroll to top',
    );
    assert.deepEqual((result.request().data as { pending: string[] }).pending, [
        'next-0',
        'next-1',
        'new-1',
        'new-2',
    ]);
});

for (const log of ['latest', 'following'] as const) {
    test(`keeps the reading row still after delayed preview and image resizing on ${log}`, () => {
        const result = insertAt(80, false, [], log);
        result.finish();
        result.refreshCounts();
        result.mutateAbove(20.75);
        assert.equal(result.readingTop(), 80);
        result.resizeAbove(256);
        assert.equal(result.readingTop(), 80);
        result.resizeAbove(-100);
        assert.equal(result.readingTop(), 80);
    });

    test(`follows deliberate scrolling before preserving later media resizing on ${log}`, () => {
        const result = insertAt(80, false, [], log);
        result.finish();
        result.scrollTo(300);
        const top = result.readingTop();
        result.resizeAbove(60);
        assert.equal(result.readingTop(), top);
        result.scrollTo(420);
        const next = result.readingTop();
        result.resizeAbove(90);
        assert.equal(result.readingTop(), next);
    });
}

test('keeps a visible focused composer in place while arrivals and previews load below it', () => {
    const result = insertAt(0, false, [], 'latest', 3, true);
    result.finish();
    assert.equal(result.composerTop(), 60);
    result.mutateAbove(80);
    assert.equal(result.composerTop(), 60);
});

test('anchors the reading row after a focused composer has scrolled out of view', () => {
    const result = insertAt(500, false, [], 'latest', 3, true);
    result.finish();
    result.resizeAbove(60);
    assert.equal(result.readingTop(), 80);
});

test('returns the anchor to the feed when the reader leaves the composer', () => {
    const result = insertAt(0, false, [], 'latest', 3, true);
    result.finish();
    result.blur();
    const top = result.readingTop();
    result.mutateAbove(80);
    assert.equal(result.readingTop(), top);
});

for (const log of ['latest', 'following'] as const) {
    test(`lets New commits finish smooth scrolling through card remeasurement on ${log}`, () => {
        const result = insertAt(1500, false, [], log);
        const button = result.finish().find((node) => node.type === 'button')!;
        window.scrollTo = () => {};
        (button.props.onClick as () => void)();
        result.progressScroll(1400, 60);
        assert.equal(result.currentScroll(), 1400);
        result.progressScroll(700, -80);
        assert.equal(result.currentScroll(), 700);
        result.progressScroll(0, 120);
        assert.equal(result.currentScroll(), 0);
        vi.advanceTimersByTime(450);
        assert.equal(
            result.render().some((node) => node.type === 'button'),
            false,
        );
    });
    test(`resumes reading preservation after an interrupted return settles on ${log}`, () => {
        const result = insertAt(1500, false, [], log);
        const button = result.finish().find((node) => node.type === 'button')!;
        window.scrollTo = () => {};
        (button.props.onClick as () => void)();
        result.progressScroll(1400, 60);
        assert.equal(result.currentScroll(), 1400);
        result.paginate();
        vi.advanceTimersByTime(150);
        const top = result.readingTop();
        result.resizeAbove(70);
        assert.equal(result.readingTop(), top);
    });
}

for (const log of ['latest', 'following'] as const) {
    test(`leaves initial media anchoring with the browser until arrivals load on ${log}`, () => {
        insertAt(500, false, [], log, 3, false, true);
        assert.equal(document.documentElement.style.overflowAnchor, '');
        const arrival = insertAt(500, false, [], log);
        assert.equal(arrival.insertionAnchoring, 'none');
        arrival.finish();
        assert.equal(document.documentElement.style.overflowAnchor, 'none');
    });
    test(`preserves intentional scrolling when preview growth occurs in the same task on ${log}`, () => {
        const result = insertAt(80, false, [], log);
        result.finish();
        const top = result.readingTop();
        const offset = result.currentScroll();
        result.progressScroll(offset + 200, 20.75);
        assert.equal(result.readingTop(), top - 200);
        assert.equal(result.currentScroll(), offset + 200 + 20.75);
    });
}

for (const log of ['following', 'latest'] as const) {
    test(`reselecting ${log} scrolls without another request and releases the reading anchor`, () => {
        const state = insertAt(700, true, [], log);
        const nodes = state.render();
        const tabs = nodes.find((node) => node.props.label === 'Logs')!.props.tabs as { title: string; onClick?: () => void }[];
        const previousCalls = reload.mock.calls.length;
        tabs.find((tab) => tab.title.toLowerCase() === log)!.onClick!();
        assert.equal(state.currentScroll(), 0);
        assert.equal(reload.mock.calls.length, previousCalls);
        assert.equal(tabs.find((tab) => tab.title === 'Trending')!.onClick, undefined);
        assert.equal(tabs.find((tab) => ['Following', 'Latest'].includes(tab.title) && tab.title.toLowerCase() !== log)!.onClick, undefined);
        state.resizeAbove(200);
        assert.equal(state.currentScroll(), 0);
    });
}

for (const lane of ['latest', 'following'] as const) {
    test(`${lane} batches arrivals while loaded removals bypass the cooldown`, async () => {
        const fake = fakeConnection();
        connectRealtime(fake.connection);
        const feed = insertAt(0, false, [], lane, 0, false, true);
        const hear = (ids: string[]) => fake.joined.get('log')!.emit('visibility', { ids });
        const finishProbe = (count: number) => {
            const options = reload.mock.calls.at(-1)![0] as ReloadOptions;
            options.onSuccess?.({ props: { newCommitsCount: count, removedCommitIds: [] } } as never);
            options.onFinish?.({} as never);
        };
        hear(['first', 'second']);
        await vi.advanceTimersByTimeAsync(999);
        assert.equal(reload.mock.calls.length, 0);
        await vi.advanceTimersByTimeAsync(1);
        assert.equal(reload.mock.calls.length, 1);
        finishProbe(2);
        await vi.advanceTimersByTimeAsync(0);
        assert.equal(reload.mock.calls.length, 2);
        const incoming = [
            { id: 'first', author: { id: 'author' } },
            { id: 'second', author: { id: 'author' } },
            ...feed.data(),
        ] as Commit[];
        const full = reload.mock.calls.at(-1)![0] as ReloadOptions;
        full.onSuccess?.({ props: { commits: { data: incoming } } } as never);
        feed.deliver(incoming);
        full.onFinish?.({} as never);
        await vi.advanceTimersByTimeAsync(0);

        hear(['third', 'fourth']);
        hear(['existing']);
        await vi.advanceTimersByTimeAsync(1_000);
        assert.equal(reload.mock.calls.length, 3);
        const removal = reload.mock.calls.at(-1)![0] as ReloadOptions;
        assert.deepEqual(removal.only, ['removedCommitIds']);
        assert.equal(removal.only?.includes('newCommitsCount'), false);
        removal.onSuccess?.({ props: { removedCommitIds: [] } } as never);
        removal.onFinish?.({} as never);
        await vi.advanceTimersByTimeAsync(3_999);
        assert.equal(reload.mock.calls.length, 3);
        await vi.advanceTimersByTimeAsync(1);
        assert.equal(reload.mock.calls.length, 4);
        assert.ok((reload.mock.calls.at(-1)![0] as ReloadOptions).only?.includes('newCommitsCount'));
        finishProbe(0);
        await vi.advanceTimersByTimeAsync(0);
    });
}

test('a hidden log retains arrivals and catches up once when shown', async () => {
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    const feed = insertAt(0, false, [], 'latest', 0, false, true);
    feed.visibility('hidden');
    fake.joined.get('log')!.emit('visibility', { ids: ['while-hidden'] });
    await vi.advanceTimersByTimeAsync(90_000);
    assert.equal(reload.mock.calls.length, 0);
    feed.visibility('visible');
    await vi.advanceTimersByTimeAsync(1_000);
    assert.equal(reload.mock.calls.length, 1);
    const recovery = reload.mock.calls.at(-1)![0] as ReloadOptions;
    assert.ok(recovery.only?.includes('windowDigest'));
    recovery.onSuccess?.({ props: { newCommitsCount: 0, removedCommitIds: [] } } as never);
    recovery.onFinish?.({} as never);
    await vi.advanceTimersByTimeAsync(60_000);
    assert.equal(reload.mock.calls.length, 1);
});

test('an arrival check that fails retries without another websocket signal', async () => {
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    insertAt(0, false, [], 'latest', 0, false, true);
    fake.joined.get('log')!.emit('visibility', { ids: ['retry-me'] });
    await vi.advanceTimersByTimeAsync(1_000);
    const failed = reload.mock.calls.at(-1)![0] as ReloadOptions;
    failed.onFinish?.({} as never);
    await vi.advanceTimersByTimeAsync(1_000);
    assert.equal(reload.mock.calls.length, 2);
    const retried = reload.mock.calls.at(-1)![0] as ReloadOptions;
    assert.deepEqual((retried.data as { arrived: string[] }).arrived, ['retry-me']);
    retried.onSuccess?.({ props: { newCommitsCount: 0, removedCommitIds: [] } } as never);
    retried.onFinish?.({} as never);
    await vi.advanceTimersByTimeAsync(0);
});

test('a later visibility signal for an incoming row is checked promptly after the full read', async () => {
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    const feed = insertAt(0, false, [], 'latest', 0, false, true);
    const hear = () => fake.joined.get('log')!.emit('visibility', { ids: ['arrived-then-deleted'] });
    hear();
    await vi.advanceTimersByTimeAsync(1_000);
    const probe = reload.mock.calls.at(-1)![0] as ReloadOptions;
    probe.onSuccess?.({ props: { newCommitsCount: 1, removedCommitIds: [] } } as never);
    probe.onFinish?.({} as never);
    await vi.advanceTimersByTimeAsync(0);
    const full = reload.mock.calls.at(-1)![0] as ReloadOptions;
    hear();
    const data = [{ id: 'arrived-then-deleted', author: { id: 'author' } }, ...feed.data()] as Commit[];
    full.onSuccess?.({ props: { commits: { data } } } as never);
    feed.deliver(data);
    full.onFinish?.({} as never);
    await vi.advanceTimersByTimeAsync(1_000);
    assert.equal(reload.mock.calls.length, 3);
    const removal = reload.mock.calls.at(-1)![0] as ReloadOptions;
    assert.deepEqual(removal.only, ['removedCommitIds']);
    assert.deepEqual((removal.data as { pending: string[] }).pending, ['arrived-then-deleted']);
    removal.onSuccess?.({ props: { removedCommitIds: ['arrived-then-deleted'] } } as never);
    removal.onFinish?.({} as never);
    await vi.advanceTimersByTimeAsync(0);
    assert.equal(feed.data().some(commit => commit.id === 'arrived-then-deleted'), false);
});

test('a failed full feed reload retains arrivals for the next read', async () => {
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    insertAt(0, false, [], 'following', 0, false, true);
    fake.joined.get('log')!.emit('visibility', { ids: ['retry-full'] });
    await vi.advanceTimersByTimeAsync(1_000);
    const probe = reload.mock.calls.at(-1)![0] as ReloadOptions;
    probe.onSuccess?.({ props: { newCommitsCount: 1, removedCommitIds: [] } } as never);
    probe.onFinish?.({} as never);
    await vi.advanceTimersByTimeAsync(0);
    const failed = reload.mock.calls.at(-1)![0] as ReloadOptions;
    failed.onFinish?.({} as never);
    await vi.advanceTimersByTimeAsync(1_000);
    assert.equal(reload.mock.calls.length, 3);
    const retry = reload.mock.calls.at(-1)![0] as ReloadOptions;
    assert.deepEqual((retry.data as { arrived: string[] }).arrived, ['retry-full']);
    retry.onSuccess?.({ props: { newCommitsCount: 0, removedCommitIds: [] } } as never);
    retry.onFinish?.({} as never);
    await vi.advanceTimersByTimeAsync(0);
});

test('hiding while an arrival probe is in flight must not start a hidden full read', async () => {
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    const feed = insertAt(0, false, [], 'latest', 0, false, true);
    fake.joined.get('log')!.emit('visibility', { ids: ['new'] });
    await vi.advanceTimersByTimeAsync(1000);
    assert.equal(reload.mock.calls.length, 1);
    const probe = reload.mock.calls[0][0] as ReloadOptions;
    feed.visibility('hidden');
    probe.onSuccess?.({props: {newCommitsCount: 1, removedCommitIds: []}} as never);
    probe.onFinish?.({} as never);
    await vi.advanceTimersByTimeAsync(0);
    assert.equal(reload.mock.calls.length, 1, 'a new HTTP request must wait until visible');
});

test('cancelling during digest must not strand loadingNew on the next log', async () => {
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    const feed = insertAt(0, false, [], 'latest', 0, false, true);
    let resolveDigest: (value: ArrayBuffer) => void = () => {};
    vi.stubGlobal('crypto', {subtle: {digest: () => new Promise<ArrayBuffer>(resolve => {resolveDigest = resolve;})}});
    feed.visibility('visible');
    await vi.advanceTimersByTimeAsync(1000);
    const oldProbe = reload.mock.calls[0][0] as ReloadOptions;
    oldProbe.onSuccess?.({props: {newCommitsCount: 1, removedCommitIds: [], windowDigest: 'different'}} as never);
    oldProbe.onFinish?.({} as never);
    feed.switchLog('following');
    resolveDigest(new ArrayBuffer(32));
    await vi.advanceTimersByTimeAsync(0);
    const before = reload.mock.calls.length;
    feed.poll(1);
    await vi.advanceTimersByTimeAsync(0);
    assert.equal(reload.mock.calls.length, before + 1, 'the new log must be able to load incoming commits');
});


test('a queued arrival read covered by an urgent full read must not issue another request', async () => {
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    const feed = insertAt(0, false, [], 'latest', 0, false, true);
    feed.visibility('visible');
    await vi.advanceTimersByTimeAsync(1000);
    const catchup = reload.mock.calls[0][0] as ReloadOptions;
    fake.joined.get('log')!.emit('visibility', {ids:['already-covered']});
    catchup.onSuccess?.({props: {newCommitsCount: 1, removedCommitIds: []}} as never);
    catchup.onFinish?.({} as never);
    await vi.advanceTimersByTimeAsync(0);
    assert.equal(reload.mock.calls.length, 2);
    const full = reload.mock.calls[1][0] as ReloadOptions;
    await vi.advanceTimersByTimeAsync(1000);
    assert.equal(reload.mock.calls.length, 2, 'arrival is serialized behind full read');
    const data = [{id:'already-covered',author:{id:'author'}},...feed.data()] as Commit[];
    full.onSuccess?.({props: {commits:{data}}} as never);
    feed.deliver(data);
    full.onFinish?.({} as never);
    await vi.advanceTimersByTimeAsync(0);
    assert.equal(reload.mock.calls.length, 2, 'full read already covered the queued arrival');
});


test('a wider arrival burst checks window membership and retains an eligible tail arrival', async () => {
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    const feed = insertAt(0, false, [], 'latest', 0, false, true);
    vi.stubGlobal('crypto', {subtle: {digest: async () => new ArrayBuffer(32)}});
    const ids = Array.from({length:205}, (_,i) => 'burst-'+i);
    fake.joined.get('log')!.emit('visibility', {ids});
    await vi.advanceTimersByTimeAsync(1000);
    const options = reload.mock.calls[0][0] as ReloadOptions;
    assert.ok(options.only?.includes('windowDigest'));
    assert.equal((options.data as {arrived:string[]}).arrived.length,100);
    options.onSuccess?.({props: {newCommitsCount: 0, arrivedCommitIds: [], removedCommitIds: [], windowDigest:'changed'}} as never);
    options.onFinish?.({} as never);
    await vi.advanceTimersByTimeAsync(0);
    assert.equal(reload.mock.calls.length,2);
    const full = reload.mock.calls[1][0] as ReloadOptions;
    const data = [{id:ids[204],author:{id:'author'}},...feed.data()] as Commit[];
    full.onSuccess?.({props:{commits:{data}}} as never);
    feed.deliver(data);
    full.onFinish?.({} as never);
    await vi.advanceTimersByTimeAsync(6000);
    assert.ok(feed.data().some(c=>c.id===ids[204]));
    assert.equal(reload.mock.calls.length,2);
});

test('cancelling during digest must not strand loadingNew on viewer change', async () => {
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    const feed = insertAt(0, false, [], 'latest', 0, false, true);
    let resolveDigest: (value: ArrayBuffer) => void = () => {};
    vi.stubGlobal('crypto', {subtle: {digest: () => new Promise<ArrayBuffer>(resolve => {resolveDigest = resolve;})}});
    feed.visibility('visible');
    await vi.advanceTimersByTimeAsync(1000);
    const oldProbe = reload.mock.calls[0][0] as ReloadOptions;
    oldProbe.onSuccess?.({props: {newCommitsCount: 1, removedCommitIds: [], windowDigest: 'different'}} as never);
    oldProbe.onFinish?.({} as never);
    viewer = {id:'replacement-viewer'} as User;
    feed.render();
    resolveDigest(new ArrayBuffer(32));
    await vi.advanceTimersByTimeAsync(0);
    const before = reload.mock.calls.length;
    feed.poll(1);
    await vi.advanceTimersByTimeAsync(0);
    assert.equal(reload.mock.calls.length, before+1, 'the replacement viewer must be able to load incoming commits');
});

for (const log of ['latest', 'following'] as const) {
    test(`${log} checks arrivals once while recovering more than a hundred loaded commits`, async () => {
        const fake = fakeConnection();
        connectRealtime(fake.connection);
        const feed = insertAt(700, false, [], log, 0, false, true);
        const loaded = [
            ...feed.data(),
            ...Array.from({ length: 150 }, (_, index) => ({ id: `loaded-${index}` })),
        ] as Commit[];
        feed.deliver(loaded);
        for (let offset = 0; offset < loaded.length; offset += 100) {
            fake.joined.get('log')!.emit('visibility', {
                ids: loaded.slice(offset, offset + 100).map(commit => commit.id),
            });
        }
        feed.visibility('visible');
        await vi.advanceTimersByTimeAsync(1_000);
        const first = reload.mock.calls[0][0] as ReloadOptions;
        assert.ok(first.only?.includes('windowDigest'));
        first.onSuccess?.({ props: { newCommitsCount: 1, removedCommitIds: [] } } as never);
        first.onFinish?.({} as never);
        await vi.advanceTimersByTimeAsync(0);
        const incoming = [{ id: 'arrival', author: { id: 'author' } }, ...loaded] as Commit[];
        const full = reload.mock.calls[1][0] as ReloadOptions;
        full.onSuccess?.({ props: { commits: { data: incoming } } } as never);
        feed.deliver(incoming);
        full.onFinish?.({} as never);
        await vi.advanceTimersByTimeAsync(0);

        const second = reload.mock.calls[2][0] as ReloadOptions;
        assert.deepEqual((second.data as { pending: string[] }).pending, loaded.slice(100).map(commit => commit.id));
        second.onSuccess?.({ props: { newCommitsCount: 1, removedCommitIds: ['loaded-149'] } } as never);
        second.onFinish?.({} as never);
        await vi.advanceTimersByTimeAsync(0);

        assert.equal(reload.mock.calls.length, 3, 'one full feed read serves every removal chunk');
        assert.deepEqual(second.only, ['removedCommitIds']);
        assert.deepEqual((second.data as { arrived: string[] }).arrived, []);
        assert.equal(feed.data().some(commit => commit.id === 'loaded-149'), false);
        const notice = feed.render().find(node => node.type === 'button')!;
        assert.equal(notice.props['aria-label'], '1 new commit above. Scroll to top');
    });
}
