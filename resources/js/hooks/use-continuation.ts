import { router, useRemember } from '@inertiajs/react';
import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { toast } from 'sonner';
import CommitContinuationController from '@/actions/App/Http/Controllers/CommitContinuationController';
import { focusCommit } from '@/hooks/use-keyboard-shortcuts';
import { idsOf } from '@/hooks/use-realtime';
import { coalesce, commitChannel, listen } from '@/lib/realtime';
import type { Commit, ThreadContinuation } from '@/types';

export function useContinuation(continuation: ThreadContinuation) {
    const [loaded, setLoaded] = useState(continuation);
    const [{ shown, nextId, size, isCapped }, setThread] = useRemember(
        {
            shown: continuation.data,
            nextId: continuation.next_id,
            size: sizeOf(continuation),
            isCapped: continuation.is_capped,
        },
        'continuation',
    );
    const [isLoading, setIsLoading] = useState(false);
    const [focusId, setFocusId] = useState<string | null>(null);
    const request = useRef<AbortController | null>(null);

    // Reloads after a delete or an undo send the first page again: pages loaded since are kept unless the thread changed.
    if (loaded !== continuation) {
        setLoaded(continuation);
        if (startsTheSame(loaded, continuation)) {
            const refreshed = new Map(
                continuation.data.map((part) => [part.id, part]),
            );
            setThread((current) => ({
                ...current,
                shown: current.shown.map(
                    (part) => refreshed.get(part.id) ?? part,
                ),
            }));
        } else {
            setThread({
                shown: continuation.data,
                nextId: continuation.next_id,
                size: sizeOf(continuation),
                isCapped: continuation.is_capped,
            });
        }
    }

    useEffect(() => () => request.current?.abort(), [continuation]);

    /**
     * Another client changing the thread reaches every part shown. A part deleted or brought back reads the thread
     * again as an undo does, keeping the pages loaded when it still starts the same, and a reply continuing the last
     * part shown is appended, without reading the rest again.
     */
    const onPartChanged = useEffectEvent(
        async (partId: string, ids: string[]) => {
            if (ids.includes(partId)) {
                router.reload({ only: ['continuation'] });

                return;
            }

            if (partId !== shown.at(-1)?.id || nextId !== null || isLoading) {
                return;
            }

            const response = await fetch(
                CommitContinuationController.index(partId).url,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            if (!response.ok) {
                return;
            }

            const page = (await response.json()) as ThreadContinuation;
            const lastPosition = continuation.position + shown.length;

            setThread((current) =>
                current.shown.at(-1)?.id === partId
                    ? {
                          shown: [
                              ...current.shown,
                              ...page.data.filter(
                                  (part) =>
                                      !current.shown.some(
                                          ({ id }) => id === part.id,
                                      ),
                              ),
                          ],
                          nextId: page.next_id,
                          size: lastPosition + page.length,
                          isCapped: page.is_capped,
                      }
                    : current,
            );
        },
    );
    const shownIds = shown.map((part) => part.id).join(' ');

    useEffect(() => {
        const stops = shownIds
            .split(' ')
            .filter(Boolean)
            .map((partId) => {
                const heard = new Set<string>();
                const refresh = coalesce(async () => {
                    const ids = [...heard];
                    heard.clear();
                    await onPartChanged(partId, ids);
                }, 300);
                const stop = listen(commitChannel(partId), {
                    visibility: (payload) => {
                        idsOf(payload).forEach((id) => heard.add(id));
                        refresh.request();
                    },
                });

                return () => {
                    stop();
                    refresh.cancel();
                };
            });

        return () => stops.forEach((stop) => stop());
    }, [shownIds]);

    useEffect(() => {
        const card =
            focusId === null
                ? null
                : document.querySelector<HTMLElement>(
                      `[data-commit="${focusId}"]`,
                  );
        if (card) {
            focusCommit(card);
        }
    }, [focusId]);

    const loadMore = () => {
        const last = shown.at(-1);
        if (last === undefined || isLoading) {
            return;
        }

        const lastPosition = continuation.position + shown.length;
        const controller = new AbortController();
        request.current = controller;
        setIsLoading(true);

        fetch(CommitContinuationController.index(last).url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Unable to load the thread');
                }

                return response.json() as Promise<ThreadContinuation>;
            })
            .then((page) => {
                setThread((current) => ({
                    shown: [...current.shown, ...page.data],
                    nextId: page.next_id,
                    size: lastPosition + page.length,
                    isCapped: page.is_capped,
                }));
                setFocusId(page.data[0]?.id ?? null);
            })
            .catch(() => {
                if (!controller.signal.aborted) {
                    toast.error(
                        'Could not load the rest of the thread. Try again.',
                    );
                }
            })
            .finally(() => setIsLoading(false));
    };

    return { shown, nextId, size, isCapped, isLoading, loadMore };
}

function sizeOf(continuation: ThreadContinuation): number {
    return continuation.position + continuation.length;
}

function startsTheSame(
    previous: ThreadContinuation,
    next: ThreadContinuation,
): boolean {
    return (
        previous.position === next.position &&
        previous.length === next.length &&
        previous.next_id === next.next_id &&
        sameIds(previous.data, next.data)
    );
}

function sameIds(previous: Commit[], next: Commit[]): boolean {
    return (
        previous.length === next.length &&
        previous.every((part, index) => part.id === next[index].id)
    );
}
