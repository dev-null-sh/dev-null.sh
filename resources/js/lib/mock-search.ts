import type { Commit, User } from '@/types';

/**
 * Stands in for CommitSearchQuery until the search page is wired to it. Ranking by shared words is a crude imitation of
 * vector distance, enough to make the results change as the query does.
 */
export const suggestedQueries = [
    'posts about indexing strategies',
    'how to read explain output',
    'caching with MongoDB',
    'real-world performance tips',
    'aggregation pipeline optimization',
    'sharding best practices',
];

const latency = 450;

function author(
    id: number,
    name: string,
    username: string,
    lane: number,
): User {
    return {
        id: `00000000-0000-4000-8000-00000000000${id}`,
        username,
        name,
        avatar_url: null,
        bio: null,
        location: null,
        website: null,
        company: null,
        has_sponsors_listing: false,
        lane,
        created_at: '2025-01-01T00:00:00Z',
        updated_at: '2025-01-01T00:00:00Z',
    };
}

const authors = [
    author(1, 'Daniel Kim', 'dankim', 0),
    author(2, 'Sara Haddad', 'sarahd', 2),
    author(3, "Liam O'Connor", 'liamoc', 1),
    author(4, 'Tanya Nguyen', 'tanyan', 3),
    author(5, 'Arjun Mehta', 'arjunm', 5),
    author(6, 'Mei Tanaka', 'meit', 4),
];

function daysAgo(days: number): string {
    return new Date(Date.now() - days * 86_400_000).toISOString();
}

function commit(
    hash: string,
    user: User,
    body: string,
    days: number,
    stars: number,
    replies: number,
): Commit {
    return {
        id: `00000000-0000-4000-8000-00000${hash}`,
        user_id: user.id,
        parent_id: null,
        body,
        body_segments: [{ type: 'text', text: body }],
        status: 'approved',
        series_id: null,
        series_position: null,
        series_size: null,
        created_at: daysAgo(days),
        updated_at: daysAgo(days),
        stars_count: stars,
        hot_score: stars + replies * 2,
        starred: false,
        user,
        replies_count: replies,
        is_saved: false,
        quoted_commit_id: null,
        images: [],
    };
}

const commits = [
    commit(
        'a3f9c21',
        authors[0],
        '5 ways to improve MongoDB query performance in production: covering indexes, explain plans, and the pitfalls we hit scaling to millions of documents.',
        3,
        142,
        28,
    ),
    commit(
        '4b1e7de',
        authors[1],
        'Finally understand the query planner: when it picks an index, when it gives up, and how to read explain() output without squinting.',
        7,
        87,
        14,
    ),
    commit(
        'e9c0a11',
        authors[2],
        'Benchmarked compound vs single-field index strategies on a real workload. Results had some surprises 👇',
        14,
        64,
        19,
    ),
    commit(
        'b7d2f88',
        authors[3],
        'Cut p95 query latency by 40% at scale: better schema design, targeted indexes, and caching only where it actually matters.',
        21,
        52,
        11,
    ),
    commit(
        'c12f9ab',
        authors[4],
        'Common MongoDB performance anti-patterns we keep seeing in code reviews, and how to fix them. Hopefully saves someone some time!',
        30,
        96,
        22,
    ),
    commit(
        'd4a8e30',
        authors[5],
        'Rewrote an aggregation pipeline to $match before $lookup and it went from 9s to 300ms. Order of stages matters more than you think.',
        5,
        73,
        9,
    ),
    commit(
        'f61b2c4',
        authors[0],
        'Sharding best practice nobody told me: pick a shard key for how you read, not how you write.',
        12,
        41,
        6,
    ),
    commit(
        '0e93d7a',
        authors[2],
        'Put Redis in front of our hottest MongoDB reads. Cache invalidation is still the hard part.',
        18,
        35,
        12,
    ),
];

function wordsOf(text: string): Set<string> {
    return new Set(text.toLowerCase().match(/[a-z0-9$]{3,}/gu) ?? []);
}

function relevance(commit: Commit, terms: Set<string>): number {
    const body = wordsOf(commit.body);

    return [...terms].filter((term) =>
        [...body].some((word) => word.startsWith(term.slice(0, 5))),
    ).length;
}

export function searchMockCommits(
    term: string,
    limit = 20,
    signal?: AbortSignal,
): Promise<Commit[]> {
    const terms = wordsOf(term);
    const ranked = commits
        .map((commit) => ({ commit, score: relevance(commit, terms) }))
        .filter(({ score }) => score > 0)
        .sort((first, second) => second.score - first.score)
        .map(({ commit }) => commit)
        .slice(0, limit);

    return new Promise((resolve, reject) => {
        const timeout = setTimeout(() => resolve(ranked), latency);

        signal?.addEventListener('abort', () => {
            clearTimeout(timeout);
            reject(signal.reason);
        });
    });
}
