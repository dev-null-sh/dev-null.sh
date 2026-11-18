import type { Commit } from '@/types';

export function firstReadCommit(
    commits: Commit[],
    readFrom: string | null,
    viewerHandle: string | undefined,
): Commit | undefined {
    if (readFrom === null) {
        return undefined;
    }

    const readFromTime = Date.parse(readFrom);
    const firstReadIndex = commits.findIndex(
        (commit) => Date.parse(commit.created_at) <= readFromTime,
    );
    const hasMissedCommit = commits
        .slice(0, Math.max(firstReadIndex, 0))
        .some((commit) => commit.author.handle !== viewerHandle);

    return hasMissedCommit ? commits[firstReadIndex] : undefined;
}
