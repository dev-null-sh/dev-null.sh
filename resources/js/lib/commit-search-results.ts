import type { Commit } from '@/types';

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
