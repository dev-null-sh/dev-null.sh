import type { Commit } from '@/types';

export function groupSearchCommits(commits: Commit[]) {
    const groups = new Map<string | null, Commit[]>([[null, []]]);

    for (const commit of commits) {
        const handle = commit.guild_handle ?? null;
        const group = groups.get(handle) ?? [];
        group.push(commit);
        groups.set(handle, group);
    }

    return [...groups].filter(([, group]) => group.length > 0);
}

export class CommitSearchResults {
    term: string;
    commits: Commit[];

    constructor(term: string, commits: Commit[]) {
        this.term = term;
        this.commits = commits;
    }

    latest(now = Date.now()): CommitSearchResults {
        const since = now - 7 * 24 * 60 * 60 * 1000;

        return new CommitSearchResults(
            this.term,
            this.commits
                .filter((commit) => Date.parse(commit.created_at) >= since)
                .sort(
                    (a, b) =>
                        Date.parse(b.created_at) - Date.parse(a.created_at),
                ),
        );
    }
}
