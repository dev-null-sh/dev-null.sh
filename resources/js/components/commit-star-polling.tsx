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
import { refreshSharedState } from '@/hooks/use-shared-state';
import type { SharedValue } from '@/hooks/use-shared-state';
import { createCommitStarMutations } from '@/lib/commit-star-mutations';
import { commitStateKey } from '@/lib/commit-state';
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
    const subscribe = useCallback(
        (subscription: Subscription) => {
            subscriptions.current.add(subscription);
            observer?.observe(subscription.element);

            return () => {
                subscriptions.current.delete(subscription);

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

            setVisible(
                new Set(
                    [...subscriptions.current]
                        .filter(({ element }) => onScreen.has(element))
                        .map(({ id }) => id),
                ),
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

                states.forEach((state) => {
                    counts.set(
                        commitStateKey('pasteis_count', state.id),
                        state.pasteis_count,
                    );
                    counts.set(
                        commitStateKey('replies_count', state.id),
                        state.replies_count,
                    );
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
        const changed = new Set<string>();
        const hidden = new Set<string>();
        const refresh = coalesce(async () => {
            const ids = [...changed].filter((id) => visible.has(id));
            const disappearing = new Set(
                [...hidden].filter((id) => ids.includes(id)),
            );
            changed.clear();
            hidden.clear();

            if (ids.length > 0 && !document.hidden) {
                await read(ids, disappearing, controller.signal);
            }
        }, 300);
        const hear = (id: string) => () => {
            changed.add(id);
            refresh.request();
        };
        const stops = [...visible].map((id) =>
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

        const interval = window.setInterval(() => {
            const unheard = [...visible].filter(
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
            stops.forEach((stop) => stop());
            refresh.cancel();
            window.clearInterval(interval);
            controller.abort();
        };
    }, [visible, read]);

    return (
        <CommitStarPollingContext value={context}>
            {children}
        </CommitStarPollingContext>
    );
}
