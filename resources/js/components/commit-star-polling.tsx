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
import { createCommitStarMutations } from '@/lib/commit-star-mutations';
import type { Commit } from '@/types';

type StarState = Pick<Commit, 'id' | 'stars_count' | 'starred'>;

type Subscription = {
    id: string;
    element: HTMLElement;
    prepare: () => ((state: StarState) => void) | null;
};

export const CommitStarPollingContext = createContext<{
    subscribe: (subscription: Subscription) => () => void;
    mutations: ReturnType<typeof createCommitStarMutations>;
} | null>(null);

export function CommitStarPolling({ children }: { children: ReactNode }) {
    const [mutations] = useState(createCommitStarMutations);
    const subscriptions = useRef(new Set<Subscription>());
    const subscribe = useCallback((subscription: Subscription) => {
        subscriptions.current.add(subscription);

        return () => {
            subscriptions.current.delete(subscription);
        };
    }, []);

    const context = useMemo(
        () => ({ subscribe, mutations }),
        [subscribe, mutations],
    );

    useEffect(() => {
        const controller = new AbortController();
        const listeners = subscriptions.current;
        let running = false;

        const poll = async () => {
            if (document.hidden || running) {
                return;
            }

            const updates = new Map<Subscription, (state: StarState) => void>();

            for (const subscription of listeners) {
                const bounds = subscription.element.getBoundingClientRect();

                if (
                    bounds.width === 0 ||
                    bounds.height === 0 ||
                    bounds.bottom <= 0 ||
                    bounds.top >= window.innerHeight ||
                    bounds.right <= 0 ||
                    bounds.left >= window.innerWidth
                ) {
                    continue;
                }

                const update = subscription.prepare();

                if (update) {
                    updates.set(subscription, update);
                }
            }

            const ids = [...new Set([...updates.keys()].map(({ id }) => id))];
            running = true;

            try {
                for (let offset = 0; offset < ids.length; offset += 100) {
                    const response = await fetch(
                        index.url({
                            query: { ids: ids.slice(offset, offset + 100) },
                        }),
                        {
                            headers: { Accept: 'application/json' },
                            cache: 'no-store',
                            signal: AbortSignal.any([
                                controller.signal,
                                AbortSignal.timeout(10_000),
                            ]),
                        },
                    );

                    if (!response.ok) {
                        return;
                    }

                    const { commits } = (await response.json()) as {
                        commits: StarState[];
                    };
                    const states = new Map(
                        commits.map((state) => [state.id, state]),
                    );

                    for (const [subscription, update] of updates) {
                        const state = states.get(subscription.id);

                        if (
                            state &&
                            listeners.has(subscription) &&
                            !controller.signal.aborted
                        ) {
                            update(state);
                        }
                    }
                }
            } catch {
                // Keep the last known counts until the next successful poll.
            } finally {
                running = false;
            }
        };

        const interval = window.setInterval(() => void poll(), 30_000);

        return () => {
            window.clearInterval(interval);
            controller.abort();
        };
    }, []);

    return (
        <CommitStarPollingContext value={context}>
            {children}
        </CommitStarPollingContext>
    );
}
