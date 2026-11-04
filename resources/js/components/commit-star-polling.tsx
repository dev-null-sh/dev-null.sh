import {
    createContext,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import type { ReactNode } from 'react';
import { index } from '@/actions/App/Http/Controllers/CommitStarCountController';
import { idsOf } from '@/hooks/use-realtime';
import { refreshSharedState, sharedValue } from '@/hooks/use-shared-state';
import type { SharedValue } from '@/hooks/use-shared-state';
import { createCommitStarMutations } from '@/lib/commit-star-mutations';
import { commitStateKey, localCountVersion } from '@/lib/commit-state';
import { deletedResources } from '@/lib/deleted-resources';
import { coalesce, commitChannel, isListening, listen } from '@/lib/realtime';
import type { Commit } from '@/types';

type StarState = Pick<Commit, 'id' | 'stars_count' | 'starred'>;

type CardState = StarState & {
    pasteis_count: number;
    replies_count: number;
};

type Subscription = {
    id: string;
    element: HTMLElement;
    prepare: () => ((state: StarState) => void) | null;
};

export const CommitStarPollingContext = createContext<{
    subscribe: (subscription: Subscription) => () => void;
    mutations: ReturnType<typeof createCommitStarMutations>;
} | null>(null);

const fallbackPoll = 30_000;

function isOnScreen(element: HTMLElement): boolean {
    const bounds = element.getBoundingClientRect();

    return !(
        bounds.width === 0 ||
        bounds.height === 0 ||
        bounds.bottom <= 0 ||
        bounds.top >= window.innerHeight ||
        bounds.right <= 0 ||
        bounds.left >= window.innerWidth
    );
}

/**
 * Keeps the counts of the cards on screen current. Each card on screen listens to its commit's channel, and what it
 * hears is read again in one batch, through the same endpoint the optimistic stars settle against. Without live
 * updates, the cards on screen are read every 30 seconds instead.
 */
export function CommitStarPolling({ children }: { children: ReactNode }) {
    const [mutations] = useState(createCommitStarMutations);
    const subscriptions = useRef(new Set<Subscription>());
    const [observer, setObserver] = useState<IntersectionObserver | null>(null);
    const [visible, setVisible] = useState<ReadonlySet<string>>(new Set());
    const shown = useRef(visible);
    const syncVisible = useRef<() => void>(() => {});
    // Kept across the cards coming and going, so what was heard isn't forgotten when the cards on screen change.
    const dirty = useRef({
        changed: new Set<string>(),
        hidden: new Set<string>(),
    });
    const subscribe = useCallback(
        (subscription: Subscription) => {
            subscriptions.current.add(subscription);
            observer?.observe(subscription.element);

            return () => {
                subscriptions.current.delete(subscription);

                if (
                    ![...subscriptions.current].some(
                        ({ id }) => id === subscription.id,
                    )
                ) {
                    setVisible((current) => {
                        if (!current.has(subscription.id)) {
                            return current;
                        }

                        const next = new Set(current);
                        next.delete(subscription.id);

                        return next;
                    });
                }

                if (
                    ![...subscriptions.current].some(
                        ({ element }) => element === subscription.element,
                    )
                ) {
                    observer?.unobserve(subscription.element);
                }
            };
        },
        [observer],
    );

    const context = useMemo(
        () => ({ subscribe, mutations }),
        [subscribe, mutations],
    );

    useEffect(() => {
        const onScreen = new Set<HTMLElement>();
        const next = new IntersectionObserver((entries) => {
            entries.forEach(({ target, isIntersecting }) => {
                if (isIntersecting) {
                    onScreen.add(target as HTMLElement);
                } else {
                    onScreen.delete(target as HTMLElement);
                }
            });

            const ids = new Set(
                [...subscriptions.current]
                    .filter(({ element }) => onScreen.has(element))
                    .map(({ id }) => id),
            );
            setVisible((current) =>
                current.size === ids.size &&
                [...ids].every((id) => current.has(id))
                    ? current
                    : ids,
            );
        });

        subscriptions.current.forEach(({ element }) => next.observe(element));
        setObserver(next);

        return () => next.disconnect();
    }, []);

    const read = useCallback(
        async (ids: string[], hidden: Set<string>, signal: AbortSignal) => {
            const listeners = subscriptions.current;
            const updates = new Map<Subscription, (state: StarState) => void>();

            for (const subscription of listeners) {
                if (!ids.includes(subscription.id)) {
                    continue;
                }

                const update = subscription.prepare();

                if (update) {
                    updates.set(subscription, update);
                }
            }

            // Counts this page changed itself while the read was out stay as they are.
            const versions = new Map(
                ids.map((id) => [id, localCountVersion('replies_count', id)]),
            );

            for (let offset = 0; offset < ids.length; offset += 100) {
                const batch = ids.slice(offset, offset + 100);
                const response = await fetch(
                    index.url({ query: { ids: batch } }),
                    {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store',
                        signal: AbortSignal.any([
                            signal,
                            AbortSignal.timeout(10_000),
                        ]),
                    },
                );

                if (!response.ok) {
                    return;
                }

                const { commits } = (await response.json()) as {
                    commits: CardState[];
                };
                const states = new Map(
                    commits.map((state) => [state.id, state]),
                );

                if (signal.aborted) {
                    return;
                }

                for (const [subscription, update] of updates) {
                    const state = states.get(subscription.id);

                    if (state && listeners.has(subscription)) {
                        update(state);
                    }
                }

                const counts = new Map<string, SharedValue>();

                // Pastéis are only ever given, so an older read can never lower what an award just raised.
                states.forEach((state) => {
                    counts.set(
                        commitStateKey('pasteis_count', state.id),
                        Math.max(
                            state.pasteis_count,
                            Number(
                                sharedValue(
                                    commitStateKey('pasteis_count', state.id),
                                ) ?? 0,
                            ),
                        ),
                    );

                    if (
                        versions.get(state.id) ===
                        localCountVersion('replies_count', state.id)
                    ) {
                        counts.set(
                            commitStateKey('replies_count', state.id),
                            state.replies_count,
                        );
                    }
                });
                refreshSharedState(counts);

                // A commit heard to change that the viewer can no longer see was deleted or hidden, and one they
                // can see again, as after undoing a deletion, gets its actions back.
                batch.forEach((id) =>
                    states.has(id)
                        ? deletedResources.restoreCommit(id)
                        : hidden.has(id) && deletedResources.markCommit(id),
                );
            }
        },
        [],
    );

    useEffect(() => {
        const controller = new AbortController();
        const changed = dirty.current.changed;
        const hidden = dirty.current.hidden;
        // What a hidden tab hears waits until it is shown, rather than being read for nobody or forgotten.
        const refresh = coalesce(async () => {
            if (document.hidden) {
                return;
            }

            const ids = [...changed].filter((id) => shown.current.has(id));
            const disappearing = new Set(
                [...hidden].filter((id) => ids.includes(id)),
            );
            ids.forEach((id) => {
                changed.delete(id);
                hidden.delete(id);
            });

            if (ids.length > 0) {
                await read(ids, disappearing, controller.signal).catch(() => {
                    ids.forEach((id) => changed.add(id));
                    disappearing.forEach((id) => hidden.add(id));
                });
            }
        }, 300);
        const onVisible = () => {
            if (!document.hidden && changed.size > 0) {
                refresh.request();
            }
        };
        document.addEventListener('visibilitychange', onVisible);
        onVisible();
        const hear = (id: string) => () => {
            changed.add(id);
            refresh.request();
        };
        const stops = new Map<string, () => void>();
        const sync = () => {
            stops.forEach((stop, id) => {
                if (!shown.current.has(id)) {
                    stop();
                    stops.delete(id);
                }
            });
            shown.current.forEach((id) => {
                if (stops.has(id)) {
                    return;
                }

                stops.set(
                    id,
                    listen(
                        commitChannel(id),
                        {
                            engagement: hear(id),
                            visibility: (payload) => {
                                if (idsOf(payload).includes(id)) {
                                    hidden.add(id);
                                }

                                hear(id)();
                            },
                        },
                        hear(id),
                    ),
                );
            });
            onVisible();
        };
        syncVisible.current = sync;
        sync();

        const interval = window.setInterval(() => {
            const unheard = [...shown.current].filter(
                (id) => !isListening(commitChannel(id)),
            );

            if (unheard.length > 0 && !document.hidden) {
                void read(
                    unheard.filter((id) =>
                        [...subscriptions.current].some(
                            (subscription) =>
                                subscription.id === id &&
                                isOnScreen(subscription.element),
                        ),
                    ),
                    new Set(),
                    controller.signal,
                ).catch(() => undefined);
            }
        }, fallbackPoll);

        return () => {
            syncVisible.current = () => {};
            stops.forEach((stop) => stop());
            refresh.cancel();
            window.clearInterval(interval);
            document.removeEventListener('visibilitychange', onVisible);
            controller.abort();
        };
    }, [read]);

    useEffect(() => {
        shown.current = visible;
        syncVisible.current();
    }, [visible]);

    return (
        <CommitStarPollingContext value={context}>
            {children}
        </CommitStarPollingContext>
    );
}
