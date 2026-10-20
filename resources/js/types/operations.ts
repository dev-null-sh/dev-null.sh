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
    name: 'translations';
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

export type MetricsRange = TrafficRange & { all_time: boolean };

export type FunnelRate = 'conversion' | 'activation' | 'retention' | 'referral';

export type Funnel = {
    visitors: number | null;
    signups: number;
    activated: number;
    activation_eligible: number;
    retained: number;
    retention_eligible: number;
    retention_horizon: 1 | 7 | 14 | null;
    retention_full_on: string | null;
    referral: { members: number; team: number } | null;
    rates: Record<FunnelRate, number | null>;
    changes: Record<FunnelRate, number | null>;
};

export type ActiveUsers = {
    daily: number;
    weekly: number;
    monthly: number;
    weekly_authors: number;
};

export type CommitResponses = {
    commits: number;
    answered: number;
    median_minutes: number | null;
    first_commits: number;
    first_answered: number;
};

export type NewcomerFollows = {
    newcomers: number;
    median_follows: number | null;
    follows: number;
    followed_back: number;
};
