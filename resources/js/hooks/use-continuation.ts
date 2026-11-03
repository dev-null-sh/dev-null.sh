import { usePage, useRemember } from '@inertiajs/react';
import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { toast } from 'sonner';
import CommitContinuationController from '@/actions/App/Http/Controllers/CommitContinuationController';
import { focusCommit } from '@/hooks/use-keyboard-shortcuts';
import { idsOf } from '@/hooks/use-realtime';
import { changesSince, readAt } from '@/lib/commit-changes';
import { coalesce, commitChannel, listen } from '@/lib/realtime';
import type { Commit, ThreadContinuation } from '@/types';

export function useContinuation(continuation: ThreadContinuation) {
    const serverNow = usePage().props.now;
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
    // As many parts as the reader loaded, so a part deleted and brought back returns the pages loaded after it.
    const loadedLength = useRef(shown.length);

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
            loadedLength.current = continuation.data.length;
            setThread({
                shown: continuation.data,
                nextId: continuation.next_id,
                size: sizeOf(continuation),
                isCapped: continuation.is_capped,
            });
        }
    }

    /**
     * One reading of the chain at a time: the earliest part known to have changed, the read on its way, and a count of
     * every change to what is shown, so a read that comes back after the reader loaded more, or after another read,
     * is dropped and made again over everything shown by then.
     */
    const sync = useRef({
        dirtyFrom: null as number | null,
        generation: 0,
        reading: null as AbortController | null,
        timer: undefined as ReturnType<typeof setTimeout> | undefined,
    });

    useEffect(() => {
        const state = sync.current;
        state.generation++;

        return () => {
            request.current?.abort();
            state.reading?.abort();
        };
    }, [continuation]);

    /**
     * Reads the chain again from just before a part another client changed, as many parts as are loaded below it,
     * and puts them in place of the ones shown: a part deleted, rejected or brought back changes the chain without
     * losing the pages loaded or the reader's place. A reply continuing the last part shown is the same, from it.
     */
    const reconcile = useEffectEvent(async () => {
        const state = sync.current;
        const current = shown;

        if (state.reading !== null || isLoading || state.dirtyFrom === null) {
            return;
        }

        const index = Math.min(state.dirtyFrom, current.length);
        state.dirtyFrom = null;
        const anchor =
            index === 0 ? current[0]?.parent_id : current[index - 1]?.id;

        if (anchor === undefined || anchor === null) {
            return;
        }

        const generation = state.generation;
        const controller = new AbortController();
        state.reading = controller;
        const wanted = Math.max(
            Math.max(current.length, loadedLength.current) - index,
            1,
        );
        const parts: Commit[] = [];
        let from = anchor;
        let first: ThreadContinuation | null = null;
        let last: ThreadContinuation | null = null;

        try {
            do {
                const response = await fetch(
                    CommitContinuationController.index(from).url,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error('Unable to read the thread again');
                }

                last = (await response.json()) as ThreadContinuation;
                first ??= last;
                parts.push(...last.data);
                from = last.data.at(-1)?.id ?? from;
            } while (
                parts.length < wanted &&
                last.next_id !== null &&
                last.data.length > 0
            );
        } catch {
            if (!controller.signal.aborted && state.generation === generation) {
                return;
            }
        } finally {
            state.reading = null;
        }

        if (
            controller.signal.aborted ||
            state.generation !== generation ||
            first === null ||
            last === null
        ) {
            markChanged(index);

            return;
        }

        const read = { first, last };
        state.generation++;
        setThread((now) => ({
            shown: [...now.shown.slice(0, index), ...parts],
            nextId: read.last.next_id,
            size: continuation.position + index + read.first.length,
            isCapped: read.last.is_capped,
        }));

        if (state.dirtyFrom !== null) {
            schedule();
        }
    });

    function schedule() {
        const state = sync.current;
        clearTimeout(state.timer);
        state.timer = setTimeout(() => void reconcile(), 300);
    }

    function markChanged(index: number) {
        const state = sync.current;
        state.dirtyFrom = Math.min(state.dirtyFrom ?? index, index);
        schedule();
    }

    const onPartChanged = useEffectEvent((partId: string, ids: string[]) => {
        const index = shown.findIndex((part) => part.id === partId);

        if (index === -1) {
            return;
        }

        if (ids.includes(partId)) {
            markChanged(index);
        } else if (index === shown.length - 1 && nextId === null) {
            markChanged(shown.length);
        }
    });

    /**
     * After reading the chain before listening, coming back from history or losing the connection, asks what changed
     * about the parts shown since, and reconciles from the first that did.
     */
    const catchUp = useEffectEvent(async () => {
        const since = readSince.current;
        readSince.current = readAt();
        const current = shown;
        const changes = await Promise.all(
            current.map((part) => changesSince(part.id, since)),
        );
        const index = changes.findIndex(
            (change, at) =>
                change === null ||
                change.changed ||
                (at === current.length - 1 && change.replies.length > 0),
        );

        if (index !== -1) {
            const change = changes[index];
            markChanged(change !== null && !change.changed ? index + 1 : index);
        }
    });
    const readSince = useRef(Math.min(serverNow - 5_000, readAt()));
    const shownIds = shown.map((part) => part.id).join(' ');

    useEffect(() => {
        const catchUps = coalesce(catchUp, 300);
        const stops = shownIds
            .split(' ')
            .filter(Boolean)
            .map((partId) =>
                listen(
                    commitChannel(partId),
                    {
                        visibility: (payload) =>
                            onPartChanged(partId, idsOf(payload)),
                    },
                    catchUps.request,
                ),
            );

        return () => {
            stops.forEach((stop) => stop());
            catchUps.cancel();
        };
    }, [shownIds]);

    useEffect(() => {
        const state = sync.current;

        return () => clearTimeout(state.timer);
    }, []);

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
        sync.current.reading?.abort();

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
                sync.current.generation++;
                loadedLength.current = Math.max(
                    loadedLength.current,
                    shown.length + page.data.length,
                );
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
            .finally(() => {
                setIsLoading(false);

                if (sync.current.dirtyFrom !== null) {
                    schedule();
                }
            });
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
