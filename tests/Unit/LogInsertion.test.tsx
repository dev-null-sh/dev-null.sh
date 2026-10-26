import assert from 'node:assert/strict';
import type { ReloadOptions } from '@inertiajs/core';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterEach, test, vi } from 'vite-plus/test';
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
    usePage: () => ({ props: { auth: { user: viewer } } }),
    usePoll: (_interval: number, options: () => ReloadOptions) => {
        pollOptions = options;
        return { stop: vi.fn() };
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
            if (name === 'scroll') scrollListeners.push(listener);
            if (name === 'focusin' || name === 'focusout')
                focusListeners.push(listener);
        },
        removeEventListener: (name: string, listener: () => void) => {
            const callbacks =
                name === 'scroll' ? scrollListeners : focusListeners;
            const index = callbacks.indexOf(listener);
            if (index !== -1) callbacks.splice(index, 1);
        },
    });
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
                      return { id: row.dataset.commit, user_id: user.id, user };
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
            nodes.filter((node) => node.props.user !== undefined).length,
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
    const previews = nodes.filter((node) => node.props.user !== undefined);
    assert.equal(previews.length, 1);
    assert.equal((previews[0].props.user as User).id, author.id);
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
                .filter((n) => n.props.user)
                .map((n) => (n.props.user as User).id),
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
            fading.filter((n) => n.props.user).length,
            before.filter((n) => n.props.user).length,
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
                user_id: 'next-author',
                user: { id: 'next-author' },
            },
            {
                id: 'next-1',
                user_id: 'next-author',
                user: { id: 'next-author' },
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
