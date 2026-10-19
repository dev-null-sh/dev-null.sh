import type { Activity } from './activity';

export type ActivityMetric = 'commits' | 'replies' | 'signups';

export type SiteActivity = Activity & { metric: ActivityMetric };

export type BreakdownRow = {
    name: string;
    count: number;
};

export type ActivityBreakdowns = Record<'statuses' | 'authors', BreakdownRow[]>;

export type QueueBacklog = {
    queue: string;
    size: number;
    oldest_job_age: number | null;
};

export type FailedJob = {
    uuid: string;
    name: string;
    queue: string;
    exception: string;
    failed_at: string;
};

export type FailedJobs = {
    total: number;
    recent: FailedJob[];
};

export type Connectivity = {
    database: boolean;
    cache: boolean;
};

export type Feature = {
    name: 'translations' | 'algolia-user-search';
    enabled: boolean;
};

export type LinkPreviewHealth = {
    pending: number;
    failed: number;
};

export type Counter = 'signups' | 'commits' | 'replies' | 'authors';

export type Today = Record<Counter, { today: number; yesterday: number }>;

export type Overall = Record<Counter, number> & { since: string | null };

export type GitHubRateLimit = {
    remaining: number;
    limit: number;
    resets_at: string;
};

export type TrafficRow = {
    name: string;
    visitors: number;
    pageviews: number;
};

export type Traffic = {
    current: number;
    totals: {
        visitors: number;
        pageviews: number;
        avg_duration: number;
        bounce_rate: number;
    };
    breakdowns: Record<
        'pages' | 'referrers' | 'countries' | 'devices' | 'browsers',
        TrafficRow[]
    >;
};

export type TrafficRange = {
    from: string;
    to: string;
};
