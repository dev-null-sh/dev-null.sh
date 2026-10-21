export type ExploreTopic = {
    id: string;
    kind: 'subject' | 'story';
    key: string;
    title: string;
    categories: string[];
    summary: string | null;
    summary_generated_at: string | null;
    commits_count: number;
    contributors_count: number;
    latest_activity_at: string;
    url: string;
    subjects: { id: string; title: string; url: string }[];
    sources?: { url: string; author: string }[];
};

export type ExploreReasons = Record<string, string>;
