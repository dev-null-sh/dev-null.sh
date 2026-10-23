import type { Commit } from './commit';

export type Log = 'following' | 'latest' | 'trending';

export type CatchUp = {
    commits: Commit[];
    commits_count: number;
    authors_count: number;
    since: string;
};
