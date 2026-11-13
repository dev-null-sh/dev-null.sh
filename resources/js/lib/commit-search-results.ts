import type { Commit } from '@/types';

export class CommitSearchResults {
    term: string;
    commits: Commit[];
    /**
     * Whether the search itself was down, so the page can say so instead of claiming nothing matched.
     */
    isUnavailable: boolean;

    constructor(term: string, commits: Commit[], isUnavailable = false) {
        this.term = term;
        this.commits = commits;
        this.isUnavailable = isUnavailable;
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
            this.isUnavailable,
        );
    }
}
