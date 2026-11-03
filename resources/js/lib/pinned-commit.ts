import type { Commit, CursorPaginated } from '@/types';

/**
 * The profile's commits with the pin as it is now: a commit pinned since the list was read shows above it and not in
 * it, and one unpinned since takes its place among the commits loaded, newest first, unless it belongs further down
 * than they reach.
 */
export function withPinChanges(
    commits: CursorPaginated<Commit>,
    pinned: Commit | null,
    unpinned: Commit[],
): CursorPaginated<Commit> {
    const oldest = commits.data.at(-1);
    const position = (commit: Commit) => `${commit.created_at}|${commit.id}`;
    const returning = unpinned.filter(
        (commit) =>
            commit.id !== pinned?.id &&
            !commits.data.some(({ id }) => id === commit.id) &&
            (commits.next_cursor === null ||
                oldest === undefined ||
                position(commit) >= position(oldest)),
    );

    return {
        ...commits,
        data: [...commits.data, ...returning]
            .filter((commit) => commit.id !== pinned?.id)
            .sort((a, b) => position(b).localeCompare(position(a))),
    };
}
