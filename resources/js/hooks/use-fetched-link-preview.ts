import { useEffect, useState } from 'react';
import type { LinkPreview } from '@/types';

const POLL_DELAYS = [1000, 2000, 3000, 5000, 8000, 13000];
const MAX_RETRY_AFTER_MS = 60_000;
const MILLISECONDS_PER_SECOND = 1000;
const HTTP_TOO_MANY_REQUESTS = 429;

type PollCycle = { refreshUrl: string; url: string; isPending: boolean };
type PollState = PollCycle & {
    fetched: LinkPreview | null;
    hasGivenUp: boolean;
};
type PreviewResponse = { link_preview: LinkPreview | null };
type PollResponse = { response: Response; preview: LinkPreview | null };

async function requestLinkPreview(
    refreshUrl: string,
    signal: AbortSignal,
): Promise<PollResponse | null> {
    try {
        const response = await fetch(refreshUrl, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal,
        });

        if (signal.aborted) {
            return null;
        }

        if (!response.ok) {
            return { response, preview: null };
        }

        const data = (await response.json()) as PreviewResponse;

        if (signal.aborted) {
            return null;
        }

        return { response, preview: data.link_preview };
    } catch {
        return null;
    }
}

function nextPollDelay(
    response: Response | null,
    fallback: number | null,
): number | null {
    if (response === null || response.ok) {
        return fallback;
    }

    if (response.status !== HTTP_TOO_MANY_REQUESTS || fallback === null) {
        return null;
    }

    return retryAfterDelay(response, fallback);
}

function retryAfterDelay(response: Response, fallback: number): number | null {
    const retryAfter = response.headers.get('Retry-After');

    if (retryAfter === null) {
        return fallback;
    }

    const seconds = Number(retryAfter);

    if (Number.isFinite(seconds)) {
        return retryAfterMilliseconds(
            seconds * MILLISECONDS_PER_SECOND,
            fallback,
        );
    }

    const date = Date.parse(retryAfter);

    if (Number.isNaN(date)) {
        return fallback;
    }

    return retryAfterMilliseconds(Math.max(date - Date.now(), 0), fallback);
}

function retryAfterMilliseconds(
    delay: number,
    fallback: number,
): number | null {
    if (delay > MAX_RETRY_AFTER_MS) {
        return null;
    }

    return Math.max(fallback, delay);
}

function isCurrentPollCycle(state: PollState, cycle: PollCycle): boolean {
    return (
        state.refreshUrl === cycle.refreshUrl &&
        state.url === cycle.url &&
        state.isPending === cycle.isPending
    );
}

function syncPollState(state: PollState, cycle: PollCycle): PollState {
    if (isCurrentPollCycle(state, cycle)) {
        return state;
    }

    return { ...cycle, fetched: null, hasGivenUp: false };
}

export function useFetchedLinkPreview(
    refreshUrl: string,
    preview: LinkPreview,
): { preview: LinkPreview; isWaiting: boolean } {
    const { url, is_pending: isPending } = preview;
    const cycle = { refreshUrl, url, isPending };
    const [pollState, setPollState] = useState<PollState>({
        ...cycle,
        fetched: null,
        hasGivenUp: false,
    });
    const currentPollState = syncPollState(pollState, cycle);

    if (currentPollState !== pollState) {
        setPollState(currentPollState);
    }

    useEffect(() => {
        if (!isPending) {
            return;
        }

        const activeCycle = { refreshUrl, url, isPending };
        const controller = new AbortController();
        let timeout = 0;

        const giveUp = () => {
            setPollState((state) => {
                if (!isCurrentPollCycle(state, activeCycle)) {
                    return state;
                }

                return { ...state, hasGivenUp: true };
            });
        };

        const poll = async (attempt: number) => {
            const result = await requestLinkPreview(
                refreshUrl,
                controller.signal,
            );

            if (controller.signal.aborted) {
                return;
            }

            const fetched = result?.preview;

            if (fetched && !fetched.is_pending) {
                setPollState((state) => {
                    if (!isCurrentPollCycle(state, activeCycle)) {
                        return state;
                    }

                    return { ...state, fetched };
                });

                return;
            }

            const delay = nextPollDelay(
                result?.response ?? null,
                POLL_DELAYS[attempt + 1] ?? null,
            );

            if (delay === null) {
                giveUp();

                return;
            }

            timeout = window.setTimeout(() => void poll(attempt + 1), delay);
        };

        timeout = window.setTimeout(() => void poll(0), POLL_DELAYS[0]);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [isPending, refreshUrl, url]);

    const current =
        isPending && currentPollState.fetched?.url === url
            ? currentPollState.fetched
            : preview;

    return {
        preview: current,
        isWaiting: current.is_pending && !currentPollState.hasGivenUp,
    };
}
