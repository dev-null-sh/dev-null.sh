import { router, usePage } from '@inertiajs/react';
import { useEffect, useState, useSyncExternalStore } from 'react';
import { show } from '@/actions/App/Http/Controllers/CommitStatusController';
import { deletedResources } from '@/lib/deleted-resources';
import { withPinChanges } from '@/lib/pinned-commit';
import { coalesce, listen } from '@/lib/realtime';
import type { Commit, CursorPaginated } from '@/types';

/**
 * The loaded list excludes its original pin. When that pin disappears, only an authorized read can distinguish an
 * unpin, which belongs back in the list, from a deletion or moderation change, which must not return an active card.
 */
export function useProfileCommits(
    profileId: string,
    commits: CursorPaginated<Commit>,
    pinned: Commit | null,
): CursorPaginated<Commit> {
    const viewer = usePage().props.auth.user?.id ?? 'guest';
    const scope = `${profileId}:${viewer}`;
    const [pins, setPins] = useState({
        scope,
        pinned,
        candidates: [] as Commit[],
    });
    const [checked, setChecked] = useState(new Map<string, Commit>());

    if (pins.scope !== scope) {
        setPins({ scope, pinned, candidates: [] });
        setChecked(new Map());
    } else if (pins.pinned?.id !== pinned?.id) {
        setPins({
            scope,
            pinned,
            candidates: [
                ...pins.candidates.filter(
                    ({ id }) => id !== pinned?.id && id !== pins.pinned?.id,
                ),
                ...(pins.pinned ? [pins.pinned] : []),
            ],
        });
        setChecked((current) => {
            const next = new Map(current);
            if (pins.pinned) next.delete(pins.pinned.id);
            return next;
        });
    }

    useSyncExternalStore(
        deletedResources.subscribe,
        () =>
            pins.candidates
                .filter(({ id }) => deletedResources.hasCommit(id))
                .map(({ id }) => id)
                .join('|'),
        () => '',
    );

    useEffect(() => {
        if (pins.scope !== scope || pins.candidates.length === 0) return;

        const controller = new AbortController();
        let revision = 0;
        const refresh = coalesce(async () => {
            const started = revision;
            await Promise.all(
                pins.candidates.map(async (commit) => {
                    try {
                        const response = await fetch(show.url(commit.id), {
                            headers: { Accept: 'application/json' },
                            cache: 'no-store',
                            signal: AbortSignal.any([
                                controller.signal,
                                AbortSignal.timeout(10_000),
                            ]),
                        });
                        const status = response.ok
                            ? ((await response.json()) as Pick<
                                  Commit,
                                  'status' | 'is_checking'
                              >)
                            : null;
                        if (controller.signal.aborted || started !== revision)
                            return;
                        if (response.status === 404) {
                            deletedResources.markCommit(commit.id);
                            setChecked((current) => {
                                const next = new Map(current);
                                next.delete(commit.id);
                                return next;
                            });
                        } else if (status) {
                            deletedResources.restoreCommit(commit.id);
                            setChecked((current) =>
                                new Map(current).set(commit.id, {
                                    ...commit,
                                    ...status,
                                }),
                            );
                        }
                    } catch {
                        // A failed read proves nothing about visibility. The next profile change or join retries it.
                    }
                }),
            );
        }, 300);
        const request = () => {
            revision++;
            refresh.request();
        };
        const stop = listen(
            { name: `profiles.${profileId}`, type: 'public' },
            { profile: request },
            request,
        );
        const stopFlash = router.on('flash', ({ detail: { flash } }) => {
            if (flash.deleted) deletedResources.markCommit(flash.deleted.id);
            if (flash.restored)
                deletedResources.restoreCommit(flash.restored.id);
            request();
        });
        request();

        return () => {
            stop();
            stopFlash();
            refresh.cancel();
            controller.abort();
        };
    }, [scope, profileId, pins.scope, pins.candidates]);

    return withPinChanges(
        commits,
        pinned,
        pins.scope === scope
            ? pins.candidates.flatMap(({ id }) => checked.get(id) ?? [])
            : [],
        deletedResources.hasCommit,
    );
}
